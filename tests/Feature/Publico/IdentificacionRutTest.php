<?php

namespace Tests\Feature\Publico;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Paso de Identificacion por RUT del flujo de reserva publico (sin login):
 * verifica si el RUT ya es paciente (protegido con Turnstile server-side) y
 * da de alta uno nuevo sin password/sin token de sesion si no lo es.
 */
class IdentificacionRutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.turnstile.secret', 'test-secret');
    }

    private function fakeTurnstile(bool $success): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => $success]),
        ]);
    }

    public function test_rechaza_si_el_token_de_turnstile_no_es_valido(): void
    {
        $this->fakeTurnstile(false);
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes/verificar-rut', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-invalido',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_rechaza_sin_secret_configurado_aunque_el_token_luzca_valido(): void
    {
        config()->set('services.turnstile.secret', null);
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes/verificar-rut', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'algun-token',
            ])
            ->assertStatus(422);

        // Nunca llego a pegarle a Cloudflare: fallo cerrado antes, por config ausente.
        Http::assertNothingSent();
    }

    public function test_indica_existe_true_para_un_rut_ya_registrado(): void
    {
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '11.111.111-1']);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes/verificar-rut', [
                'rut' => '11111111-1',
                'turnstile_token' => 'token-valido',
            ])
            ->assertOk()
            ->assertExactJson(['data' => ['existe' => true]]);
    }

    public function test_indica_existe_false_para_un_rut_no_registrado(): void
    {
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes/verificar-rut', [
                'rut' => '22.222.222-2',
                'turnstile_token' => 'token-valido',
            ])
            ->assertOk()
            ->assertExactJson(['data' => ['existe' => false]]);
    }

    public function test_un_rut_de_otro_tenant_no_cuenta_como_existente(): void
    {
        $this->fakeTurnstile(true);
        $otroTenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $otroTenant->id, 'rut' => '11.111.111-1']);

        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes/verificar-rut', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-valido',
            ])
            ->assertOk()
            ->assertExactJson(['data' => ['existe' => false]]);
    }

    public function test_registra_un_paciente_nuevo_sin_password_ni_token_de_sesion(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes', [
                'rut' => '15.234.567-8',
                'nombre' => 'Pedro',
                'apellido' => 'Paciente',
                'email' => 'pedro@example.com',
                'telefono' => '+56911111111',
                'fecha_nacimiento' => '1990-05-15',
                'acepta_tratamiento_datos' => true,
            ])
            ->assertCreated();

        $response->assertJson(['data' => [
            'nombre' => 'Pedro',
            'apellido' => 'Paciente',
        ]]);

        // Sin token: la respuesta no incluye ninguna credencial de sesion.
        $response->assertJsonMissingPath('data.token');

        $patient = Patient::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNull($patient->password);
        $this->assertNotNull($patient->datos_aceptados_at);
        $this->assertSame('Paciente', $patient->apellido);
    }

    public function test_email_es_opcional_al_registrar(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes', [
                'rut' => '15.234.567-8',
                'nombre' => 'Pedro',
                'apellido' => 'Paciente',
                'telefono' => '+56911111111',
                'fecha_nacimiento' => '1990-05-15',
                'acepta_tratamiento_datos' => true,
            ])
            ->assertCreated();
    }

    public function test_no_registra_sin_aceptar_el_tratamiento_de_datos(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes', [
                'rut' => '15.234.567-8',
                'nombre' => 'Pedro',
                'apellido' => 'Paciente',
                'telefono' => '+56911111111',
                'fecha_nacimiento' => '1990-05-15',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acepta_tratamiento_datos']);
    }

    public function test_no_registra_con_un_rut_ya_existente_en_el_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '15.234.567-8']);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/pacientes', [
                'rut' => '15.234.567-8',
                'nombre' => 'Otro',
                'apellido' => 'Paciente',
                'telefono' => '+56922222222',
                'fecha_nacimiento' => '1990-05-15',
                'acepta_tratamiento_datos' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rut']);
    }
}
