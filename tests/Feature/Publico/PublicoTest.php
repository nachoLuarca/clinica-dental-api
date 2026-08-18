<?php

namespace Tests\Feature\Publico;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * Sitio publico de pacientes (sin login): marca de la clinica y gestion de
 * citas por RUT + fecha de nacimiento.
 */
class PublicoTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_marca_de_la_clinica_es_publica(): void
    {
        $tenant = Tenant::factory()->create(['nombre' => 'Clinica Publica', 'color_primario' => '#123456']);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/tenant')
            ->assertOk()
            ->assertExactJson(['data' => [
                'nombre' => 'Clinica Publica',
                'logo_url' => null,
                'color_primario' => '#123456',
                // Contraste WCAG sobre #123456 (azul oscuro): blanco.
                'color_contraste' => '#ffffff',
            ]]);
    }

    public function test_marca_publica_sin_header_de_clinica_falla(): void
    {
        $this->getJson('/api/publico/tenant')->assertStatus(400);
    }

    public function test_color_contraste_es_negro_sobre_un_color_primario_claro(): void
    {
        $tenant = Tenant::factory()->create(['color_primario' => '#FFEE88']);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/tenant')
            ->assertOk()
            ->assertJsonPath('data.color_contraste', '#000000');
    }

    public function test_color_contraste_es_null_sin_color_primario(): void
    {
        $tenant = Tenant::factory()->create(['color_primario' => null]);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/tenant')
            ->assertOk()
            ->assertJsonPath('data.color_contraste', null);
    }

    public function test_lista_citas_por_rut_y_fecha_de_nacimiento(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'rut' => '11.111.111-1',
            'fecha_nacimiento' => '1990-05-15',
        ]);
        [, $token] = [$patient, $patient->createToken('paciente', ['paciente'])->plainTextToken];

        $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        // RUT con puntos y formato distinto al guardado: debe normalizar igual.
        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/citas?rut=11111111-1&fecha_nacimiento=1990-05-15')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_rut_correcto_con_fecha_incorrecta_no_encuentra_nada(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'rut' => '22.222.222-2',
            'fecha_nacimiento' => '1990-05-15',
        ]);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/citas?rut=22222222-2&fecha_nacimiento=2000-01-01')
            ->assertStatus(422);
    }

    public function test_puede_cancelar_su_cita_sin_login(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'rut' => '33.333.333-3',
            'fecha_nacimiento' => '1985-01-01',
        ]);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $appointment = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated()->json('data');

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->deleteJson("/api/publico/citas/{$appointment['id']}", [
                'rut' => '33333333-3',
                'fecha_nacimiento' => '1985-01-01',
            ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');
    }

    public function test_no_puede_cancelar_la_cita_de_otro_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);

        $dueno = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $token = $dueno->createToken('paciente', ['paciente'])->plainTextToken;
        $appointment = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated()->json('data');

        $otro = Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'rut' => '44.444.444-4',
            'fecha_nacimiento' => '1970-02-02',
        ]);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->deleteJson("/api/publico/citas/{$appointment['id']}", [
                'rut' => '44444444-4',
                'fecha_nacimiento' => '1970-02-02',
            ])
            ->assertNotFound();
    }
}
