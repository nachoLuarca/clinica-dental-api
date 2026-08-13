<?php

namespace Tests\Feature\Seguridad;

use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Endurecimiento (paso 7): endpoints publicos (catalogo y disponibilidad) con
 * rate limiting por tenant + IP y resolucion de tenant por slug de clinica.
 */
class PublicRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_catalogo_publico_devuelve_tratamientos_del_tenant_del_header(): void
    {
        $tenant = Tenant::factory()->create();
        Treatment::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        // Tratamiento de otra clinica: no debe filtrarse.
        Treatment::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/tratamientos')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_catalogo_publico_sin_header_de_clinica_falla(): void
    {
        $this->getJson('/api/publico/tratamientos')
            ->assertStatus(400);
    }

    public function test_catalogo_publico_con_clinica_inexistente_da_404(): void
    {
        $this->withHeader('X-Clinica', 'clinica-que-no-existe')
            ->getJson('/api/publico/tratamientos')
            ->assertNotFound();
    }

    public function test_endpoint_publico_responde_429_al_superar_el_limite(): void
    {
        $tenant = Tenant::factory()->create();
        $limite = (int) config('seguridad.rate_limits.publico'); // 30/min

        // Hasta el limite: OK.
        for ($i = 0; $i < $limite; $i++) {
            $this->withHeader('X-Clinica', $tenant->slug)
                ->getJson('/api/publico/tratamientos')
                ->assertOk();
        }

        // La siguiente peticion supera el cupo -> 429.
        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/tratamientos')
            ->assertStatus(429);
    }

    public function test_el_limite_publico_es_por_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $limite = (int) config('seguridad.rate_limits.publico');

        // Agota el cupo del tenant A.
        for ($i = 0; $i < $limite + 1; $i++) {
            $this->withHeader('X-Clinica', $tenantA->slug)->getJson('/api/publico/tratamientos');
        }

        $this->withHeader('X-Clinica', $tenantA->slug)
            ->getJson('/api/publico/tratamientos')
            ->assertStatus(429);

        // El tenant B (misma IP) conserva su propio cupo: no queda bloqueado.
        $this->withHeader('X-Clinica', $tenantB->slug)
            ->getJson('/api/publico/tratamientos')
            ->assertOk();
    }
}
