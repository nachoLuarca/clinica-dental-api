<?php

namespace Tests\Feature\Cruds;

use App\Models\Budget;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\Treatment;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class BudgetCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_crea_presupuesto_y_el_total_se_calcula_del_servidor(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $limpieza = Treatment::factory()->create(['tenant_id' => $tenant->id, 'precio' => 25000]);

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/budgets', [
                'patient_id' => $patient->id,
                'estado' => 'borrador',
                'total' => 999999, // debe IGNORARSE
                'items' => [
                    ['treatment_id' => $limpieza->id, 'cantidad' => 2], // 50000
                    ['nombre' => 'Atencion diferencial', 'precio_unitario' => 15000, 'cantidad' => 1], // 15000
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.estado', 'borrador')
            ->assertJsonCount(2, 'data.items');

        // total = 2*25000 + 15000 = 65000, no el 999999 enviado por el cliente.
        $this->assertEquals(65000, (float) $response->json('data.total'));
        $this->assertDatabaseHas('budgets', ['patient_id' => $patient->id, 'total' => 65000]);
        $this->assertDatabaseCount('budget_items', 2);
    }

    public function test_linea_con_tratamiento_toma_snapshot_de_nombre_y_precio(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $t = Treatment::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Extraccion', 'precio' => 40000]);

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/budgets', [
                'patient_id' => $patient->id,
                'items' => [['treatment_id' => $t->id]],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.nombre', 'Extraccion');
        $this->assertEquals(40000, (float) $response->json('data.items.0.precio_unitario'));
    }

    public function test_no_se_puede_usar_tratamiento_de_otro_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otro = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $ajeno = Treatment::factory()->create(['tenant_id' => $otro->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/budgets', [
                'patient_id' => $patient->id,
                'items' => [['treatment_id' => $ajeno->id]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_no_se_puede_presupuestar_paciente_de_otro_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otro = Tenant::factory()->create();
        $ajeno = Patient::factory()->create(['tenant_id' => $otro->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/budgets', [
                'patient_id' => $ajeno->id,
                'items' => [['nombre' => 'X', 'precio_unitario' => 1000]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['patient_id']);
    }

    public function test_linea_diferencial_sin_nombre_o_precio_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/budgets', [
                'patient_id' => $patient->id,
                'items' => [['cantidad' => 1]],
            ])
            ->assertStatus(422);
    }

    public function test_actualizar_estado_sin_tocar_lineas(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $budget = Budget::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'total' => 50000]);

        // Se crea la linea fuera de un request: fijamos el tenant en el contexto
        // para que BelongsToTenant asigne el tenant_id (no es un campo fillable).
        app(TenantContext::class)->runWithTenant($tenant->id, function () use ($budget) {
            $budget->items()->create([
                'nombre' => 'X',
                'precio_unitario' => 50000,
                'cantidad' => 1,
                'subtotal' => 50000,
            ]);
        });

        $this->withToken($this->staffTokenFor($tenant))
            ->putJson("/api/staff/budgets/{$budget->id}", ['estado' => 'aceptado'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'aceptado');

        // El total y las lineas no cambian.
        $this->assertEquals(50000, (float) $budget->fresh()->total);
        $this->assertDatabaseCount('budget_items', 1);
    }

    public function test_actualizar_lineas_recalcula_total(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $budget = Budget::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id, 'total' => 0]);

        $this->withToken($this->staffTokenFor($tenant))
            ->putJson("/api/staff/budgets/{$budget->id}", [
                'items' => [['nombre' => 'Nueva', 'precio_unitario' => 10000, 'cantidad' => 3]],
            ])
            ->assertOk();

        $this->assertEquals(30000, (float) $budget->fresh()->total);
    }

    public function test_estado_invalido_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/budgets', [
                'patient_id' => $patient->id,
                'estado' => 'inexistente',
                'items' => [['nombre' => 'X', 'precio_unitario' => 1000]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado']);
    }

    public function test_staff_lista_y_elimina_presupuesto(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->staffTokenFor($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $budget = Budget::factory()->create(['tenant_id' => $tenant->id, 'patient_id' => $patient->id]);

        $this->withToken($token)->getJson('/api/staff/budgets')
            ->assertOk()->assertJsonStructure(['data', 'meta', 'links']);

        $this->withToken($token)->deleteJson("/api/staff/budgets/{$budget->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }
}
