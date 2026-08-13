<?php

namespace Tests\Feature\Cruds;

use App\Models\Budget;
use App\Models\Patient;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

/**
 * Aislamiento multi-tenant a nivel de endpoints HTTP: el staff de un tenant no
 * ve ni accede a recursos de otro tenant. El tenant sale del token del staff,
 * nunca del request.
 */
class CrudsTenantIsolationTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_listados_solo_muestran_recursos_del_propio_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Professional::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Professional::factory()->count(4)->create(['tenant_id' => $tenantB->id]);
        Treatment::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        Treatment::factory()->count(1)->create(['tenant_id' => $tenantB->id]);

        $tokenA = $this->staffTokenFor($tenantA);

        $this->withToken($tokenA)->getJson('/api/staff/professionals')
            ->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($tokenA)->getJson('/api/staff/treatments')
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_no_se_puede_ver_profesional_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $profB = Professional::factory()->create(['tenant_id' => $tenantB->id]);

        $this->withToken($this->staffTokenFor($tenantA))
            ->getJson("/api/staff/professionals/{$profB->id}")
            ->assertNotFound();
    }

    public function test_no_se_puede_actualizar_tratamiento_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $tB = Treatment::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'Ajeno']);

        $this->withToken($this->staffTokenFor($tenantA))
            ->putJson("/api/staff/treatments/{$tB->id}", ['nombre' => 'Hackeado'])
            ->assertNotFound();

        $this->assertDatabaseHas('treatments', ['id' => $tB->id, 'nombre' => 'Ajeno']);
    }

    public function test_no_se_puede_eliminar_presupuesto_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
        $budgetB = Budget::factory()->create(['tenant_id' => $tenantB->id, 'patient_id' => $patientB->id]);

        $this->withToken($this->staffTokenFor($tenantA))
            ->deleteJson("/api/staff/budgets/{$budgetB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('budgets', ['id' => $budgetB->id]);
    }

    public function test_no_se_puede_ver_paciente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);

        $this->withToken($this->staffTokenFor($tenantA))
            ->getJson("/api/staff/patients/{$patientB->id}")
            ->assertNotFound();
    }
}
