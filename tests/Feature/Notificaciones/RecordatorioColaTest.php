<?php

namespace Tests\Feature\Notificaciones;

use App\Jobs\EnviarNotificacionCita;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Notificaciones\MensajeNotificacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * El recordatorio automatico va en una cola DISTINTA a la de notificaciones
 * inmediatas (colas separadas por tipo de trabajo), y el comando es idempotente.
 */
class RecordatorioColaTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    public function test_recordatorio_se_encola_en_cola_separada_y_no_en_la_inmediata(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        // Cita dentro de la ventana de recordatorio (horas_antes por defecto = 24).
        $horas = (int) config('notificaciones.recordatorio.horas_antes');
        $cita = Appointment::factory()->at(Carbon::now()->addHours($horas)->addMinutes(10), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);

        $this->artisan('citas:recordatorios')->assertSuccessful();

        $colaRecordatorios = config('notificaciones.colas.recordatorios');
        $colaInmediata = config('notificaciones.colas.inmediata');

        // Se encolo el recordatorio en la cola de recordatorios...
        Queue::assertPushed(EnviarNotificacionCita::class, function (EnviarNotificacionCita $job) use ($colaRecordatorios) {
            return $job->mensaje->tipo === MensajeNotificacion::TIPO_RECORDATORIO
                && $job->queue === $colaRecordatorios;
        });

        // ...y NADA en la cola de notificaciones inmediatas.
        Queue::assertNotPushed(EnviarNotificacionCita::class, fn (EnviarNotificacionCita $job) => $job->queue === $colaInmediata);

        // La cola de recordatorios debe ser distinta de la inmediata.
        $this->assertNotSame($colaInmediata, $colaRecordatorios);

        // La cita queda marcada como recordada (idempotencia).
        $this->assertNotNull($cita->fresh()->recordatorio_enviado_at);
    }

    public function test_recordatorio_no_se_reenvia_si_ya_fue_recordado(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        $horas = (int) config('notificaciones.recordatorio.horas_antes');
        Appointment::factory()->at(Carbon::now()->addHours($horas)->addMinutes(10), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
            'recordatorio_enviado_at' => Carbon::now(),
        ]);

        $this->artisan('citas:recordatorios')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_recordatorio_ignora_citas_fuera_de_la_ventana(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        // Cita muy lejana: fuera de la ventana de recordatorio.
        Appointment::factory()->at(Carbon::now()->addDays(30), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);

        $this->artisan('citas:recordatorios')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
