<?php

namespace Tests\Feature\Notificaciones;

use App\Jobs\EnviarNotificacionCita;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Notificaciones\MensajeNotificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithReservas;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

/**
 * Verifica que crear/cancelar una cita ENCOLA las notificaciones (no las envia
 * en linea), sobre la cola de notificaciones inmediatas, un job por canal.
 */
class NotificacionesEncoladasTest extends TestCase
{
    use InteractsWithReservas, InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_crear_cita_encola_una_notificacion_por_canal_en_la_cola_inmediata(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        // Dos canales activos (correo + whatsapp) => dos jobs, ambos de confirmacion.
        Queue::assertPushed(EnviarNotificacionCita::class, 2);

        Queue::assertPushed(EnviarNotificacionCita::class, function (EnviarNotificacionCita $job) {
            return $job->mensaje->tipo === MensajeNotificacion::TIPO_CONFIRMACION
                && $job->queue === config('notificaciones.colas.inmediata');
        });

        // Un job por cada canal configurado.
        foreach (['correo', 'whatsapp'] as $canal) {
            Queue::assertPushed(EnviarNotificacionCita::class, fn (EnviarNotificacionCita $job) => $job->canal === $canal);
        }
    }

    public function test_cancelar_cita_encola_notificacion_de_cancelacion(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient, $token] = $this->pacienteConToken($tenant);

        $cita = Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/paciente/appointments/{$cita->id}")
            ->assertOk();

        Queue::assertPushed(EnviarNotificacionCita::class, function (EnviarNotificacionCita $job) {
            return $job->mensaje->tipo === MensajeNotificacion::TIPO_CANCELACION
                && $job->queue === config('notificaciones.colas.inmediata');
        });
    }

    public function test_cancelar_una_cita_ya_cancelada_no_encola_notificacion(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient, $token] = $this->pacienteConToken($tenant);

        $cita = Appointment::factory()->cancelada()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/paciente/appointments/{$cita->id}")
            ->assertOk();

        Queue::assertNothingPushed();
    }
}
