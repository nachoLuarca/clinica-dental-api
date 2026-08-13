<?php

namespace Tests\Feature\Seguridad;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Endurecimiento (paso 7): CORS acotado a los dominios de los dos frontends,
 * configurado por .env, nunca '*'.
 */
class CorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_la_config_de_cors_esta_acotada_y_no_usa_comodin(): void
    {
        $origins = config('cors.allowed_origins');

        $this->assertIsArray($origins);
        $this->assertNotContains('*', $origins, 'CORS nunca debe permitir todos los origenes.');
        $this->assertNotEmpty($origins);
    }

    public function test_acepta_un_origen_permitido(): void
    {
        Config::set('cors.allowed_origins', ['http://localhost:5173']);
        $tenant = Tenant::factory()->create();

        $this->withHeaders([
            'X-Clinica' => $tenant->slug,
            'Origin' => 'http://localhost:5173',
        ])
            ->getJson('/api/publico/tratamientos')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_rechaza_un_origen_no_permitido(): void
    {
        Config::set('cors.allowed_origins', ['http://localhost:5173']);
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'X-Clinica' => $tenant->slug,
            'Origin' => 'https://sitio-malicioso.example',
        ])->getJson('/api/publico/tratamientos');

        // El servidor no emite el header de permiso para un origen no listado:
        // el navegador bloqueara la respuesta cross-site.
        $this->assertNotSame(
            'https://sitio-malicioso.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_preflight_de_origen_permitido_autoriza_metodo(): void
    {
        Config::set('cors.allowed_origins', ['http://localhost:5174']);

        $response = $this->call('OPTIONS', '/api/publico/tratamientos', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5174',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5174');
    }
}
