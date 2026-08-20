<?php

namespace Tests\Feature\Publico;

use App\Jobs\EnviarNotificacionCita;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * Paso Confirmar del flujo de reserva publico (sin login): crea la cita
 * identificando al paciente por RUT + Turnstile (paso 1, Identificacion),
 * reutilizando el mismo AppointmentService -mismo bloqueo optimista, mismo
 * 409- que el paciente autenticado.
 */
class ReservaPublicaTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('services.turnstile.secret', 'test-secret');
    }

    private function fakeTurnstile(bool $success): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => $success])]);
    }

    public function test_crea_una_cita_para_el_paciente_identificado_por_rut(): void
    {
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '11.111.111-1']);

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-valido',
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        $response->assertJsonPath('data.patient_id', $patient->id);
        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
            'professional_id' => $prof->id,
        ]);
    }

    public function test_modo_cualquier_profesional_disponible_tambien_funciona_sin_login(): void
    {
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '11.111.111-1']);

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-valido',
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        $response->assertJsonPath('data.professional_id', $prof->id);
    }

    public function test_devuelve_409_si_otro_se_gana_el_horario_justo_antes(): void
    {
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '11.111.111-1']);

        Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-valido',
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(409);
    }

    public function test_no_crea_cita_para_un_rut_que_no_paso_por_identificacion(): void
    {
        $this->fakeTurnstile(true);
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '99.999.999-9',
                'turnstile_token' => 'token-valido',
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(404);
    }

    public function test_no_crea_cita_sin_un_token_de_turnstile_valido(): void
    {
        $this->fakeTurnstile(false);

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '11.111.111-1']);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-invalido',
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['turnstile_token']);
    }

    public function test_un_rut_de_otro_tenant_no_permite_reservar(): void
    {
        $this->fakeTurnstile(true);
        $otroTenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $otroTenant->id, 'rut' => '11.111.111-1']);

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-valido',
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(404);
    }

    public function test_encola_notificacion_de_confirmacion_igual_que_la_reserva_autenticada(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        Patient::factory()->create(['tenant_id' => $tenant->id, 'rut' => '11.111.111-1', 'telefono' => '+56911111111']);

        $this->fakeTurnstile(true);
        Queue::fake();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->postJson('/api/publico/citas', [
                'rut' => '11.111.111-1',
                'turnstile_token' => 'token-valido',
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        Queue::assertPushed(EnviarNotificacionCita::class);
    }
}
