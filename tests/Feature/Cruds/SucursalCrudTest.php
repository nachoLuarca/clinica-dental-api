<?php

namespace Tests\Feature\Cruds;

use App\Models\Sucursal;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class SucursalCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_puede_listar_sucursales_paginadas(): void
    {
        $tenant = Tenant::factory()->create();
        Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Centro']);
        Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Norte']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/sucursales')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total'], 'links'])
            ->assertJsonCount(2, 'data');
    }

    public function test_staff_puede_crear_sucursal_con_horario_que_varia_por_dia(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/sucursales', [
                'nombre' => 'Sede Providencia',
                'direccion' => 'Av. Providencia 1234',
                'comuna' => 'Providencia',
                'telefono' => '+56221234567',
                'horarios' => [
                    ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '19:00'],
                    ['dia_semana' => 2, 'hora_inicio' => '09:00', 'hora_fin' => '19:00'],
                    ['dia_semana' => 6, 'hora_inicio' => '09:00', 'hora_fin' => '14:00'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Sede Providencia')
            ->assertJsonPath('data.comuna', 'Providencia')
            ->assertJsonCount(3, 'data.horarios');

        $this->assertDatabaseHas('sucursales', ['nombre' => 'Sede Providencia', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseCount('sucursal_schedules', 3);
    }

    public function test_staff_puede_actualizar_y_reemplazar_el_horario(): void
    {
        $tenant = Tenant::factory()->create();
        $sucursal = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Centro']);
        app(TenantContext::class)->runWithTenant($tenant->id, function () use ($sucursal) {
            $sucursal->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '13:00']);
        });

        $this->withToken($this->staffTokenFor($tenant))
            ->putJson("/api/staff/sucursales/{$sucursal->id}", [
                'horarios' => [
                    ['dia_semana' => 2, 'hora_inicio' => '10:00', 'hora_fin' => '18:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.horarios');

        $this->assertDatabaseCount('sucursal_schedules', 1);
        $this->assertDatabaseHas('sucursal_schedules', ['sucursal_id' => $sucursal->id, 'dia_semana' => 2]);
    }

    public function test_staff_puede_eliminar_sucursal(): void
    {
        $tenant = Tenant::factory()->create();
        $sucursal = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Centro']);

        $this->withToken($this->staffTokenFor($tenant))
            ->deleteJson("/api/staff/sucursales/{$sucursal->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sucursales', ['id' => $sucursal->id]);
    }

    public function test_no_ve_sucursales_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        Sucursal::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ajena']);

        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/sucursales')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_profesional_puede_quedar_asignado_a_una_sucursal(): void
    {
        $tenant = Tenant::factory()->create();
        $sucursal = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Centro']);

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [
                'nombre' => 'Ana',
                'sucursal_id' => $sucursal->id,
            ]);

        $response->assertCreated()->assertJsonPath('data.sucursal_id', $sucursal->id);
    }

    public function test_no_se_puede_asignar_una_sucursal_de_otro_tenant_a_un_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $otroTenant = Tenant::factory()->create();
        $sucursalAjena = Sucursal::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ajena']);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [
                'nombre' => 'Ana',
                'sucursal_id' => $sucursalAjena->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sucursal_id']);
    }
}
