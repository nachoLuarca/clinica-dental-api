<?php

namespace Tests\Feature\Seguridad;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Endurecimiento (paso 7): headers de seguridad basicos en las respuestas.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_las_respuestas_incluyen_headers_de_seguridad(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/tratamientos')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_la_documentacion_tiene_una_csp_propia_que_permite_el_cdn_de_swagger(): void
    {
        $this->get('/api/documentation')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
                "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
                "img-src 'self' data: https://cdn.jsdelivr.net",
                "connect-src 'self'",
                "frame-ancestors 'none'",
            ]));
    }
}
