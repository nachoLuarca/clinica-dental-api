<?php

namespace Tests\Feature\Publico;

use App\Models\Convenio;
use App\Models\Sucursal;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Secciones informativas del sitio del paciente (sin login): sucursales y
 * convenios de la clinica.
 */
class SucursalesConveniosPublicosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_lista_sucursales_activas_con_direccion_y_horario(): void
    {
        $tenant = Tenant::factory()->create();
        $sucursal = Sucursal::create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Sede Centro',
            'direccion' => 'Alameda 123',
            'comuna' => 'Santiago',
            'telefono' => '+56221111111',
        ]);
        app(TenantContext::class)->runWithTenant($tenant->id, function () use ($sucursal) {
            $sucursal->horarios()->create(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '19:00']);
        });

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/sucursales')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nombre', 'Sede Centro');
        $response->assertJsonPath('data.0.comuna', 'Santiago');
        $response->assertJsonCount(1, 'data.0.horarios');
        $response->assertJsonPath('data.0.horarios.0.dia_semana', 1);
    }

    public function test_no_lista_sucursales_inactivas(): void
    {
        $tenant = Tenant::factory()->create();
        Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Cerrada', 'activo' => false]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/sucursales')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_no_muestra_sucursales_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        Sucursal::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ajena']);

        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/sucursales')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_lista_convenios_activos(): void
    {
        $tenant = Tenant::factory()->create();
        Convenio::create(['tenant_id' => $tenant->id, 'nombre' => 'Fonasa', 'tipo' => 'fonasa']);
        Convenio::create(['tenant_id' => $tenant->id, 'nombre' => 'Isapre X', 'tipo' => 'isapre', 'activo' => false]);

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/convenios')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nombre', 'Fonasa');
    }

    public function test_no_muestra_convenios_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        Convenio::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ajeno', 'tipo' => 'fonasa']);

        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/convenios')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
