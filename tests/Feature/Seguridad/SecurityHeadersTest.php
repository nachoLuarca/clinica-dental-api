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
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }
}
