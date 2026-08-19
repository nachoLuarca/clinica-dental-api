<?php

namespace Tests\Feature\Seguridad;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Todos los errores que la API le manda a los frontends deben estar en
 * espanol neutro (sin voseo ni regionalismos, y sin depender del locale por
 * defecto de Laravel que es ingles). Cubre los dos casos que antes escapaban
 * a los render() de bootstrap/app.php: validacion (422, mensajes por
 * defecto de Laravel) y rate limiting (429, mensaje hardcodeado en el
 * framework).
 */
class ErroresEnEspanolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_los_errores_de_validacion_estan_en_espanol(): void
    {
        $this->postJson('/api/staff/login', [])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'clinica' => ['El campo clinica es obligatorio.'],
                    'email' => ['El campo email es obligatorio.'],
                    'password' => ['El campo password es obligatorio.'],
                ],
            ]);
    }

    public function test_el_error_de_rate_limit_esta_en_espanol_y_no_filtra_stack_trace(): void
    {
        RateLimiter::clear('login:'.request()->ip());

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/staff/login', []);
        }

        $this->postJson('/api/staff/login', [])
            ->assertStatus(429)
            ->assertExactJson([
                'message' => 'Demasiados intentos. Intenta de nuevo en unos minutos.',
                'error' => 'demasiados_intentos',
            ]);
    }

    public function test_el_error_de_clinica_inexistente_esta_en_espanol(): void
    {
        $this->withHeader('X-Clinica', 'no-existe')
            ->getJson('/api/publico/tratamientos')
            ->assertStatus(404)
            ->assertJson(['message' => 'La clinica indicada no existe o no esta disponible.']);
    }

    public function test_el_error_de_no_autenticado_esta_en_espanol(): void
    {
        Tenant::factory()->create();

        $this->getJson('/api/staff/me')
            ->assertStatus(401)
            ->assertExactJson([
                'message' => 'No estas autenticado. Inicia sesion para continuar.',
                'error' => 'no_autenticado',
            ]);
    }
}
