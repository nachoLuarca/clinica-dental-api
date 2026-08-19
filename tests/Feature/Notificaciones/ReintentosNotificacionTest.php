<?php

namespace Tests\Feature\Notificaciones;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * Reintentos best-effort por canal (config 'notificaciones.reintentos'): un
 * fallo transitorio (ej. WhatsApp caido un segundo) se reintenta antes de
 * darlo por perdido; solo se descarta si TODOS los intentos fallan.
 *
 * Igual que NotificacionesBestEffortTest, corre con el driver 'sync' de
 * testing para observar el comportamiento real del job en linea.
 */
class ReintentosNotificacionTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('queue.default', 'sync');
    }

    public function test_reintenta_y_se_recupera_de_un_fallo_transitorio_del_canal(): void
    {
        Mail::fake();
        config()->set('notificaciones.reintentos.intentos', 3);

        // Los primeros dos intentos fallan, el tercero (ultimo) tiene exito.
        Http::fake([
            '*/mensajes' => Http::sequence()
                ->push(['ok' => false], 503)
                ->push(['ok' => false], 503)
                ->push(['ok' => true], 200),
        ]);

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'telefono' => '+56911111111']);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        // Se agotaron los 3 intentos configurados (2 fallos + 1 exito).
        Http::assertSentCount(3);
    }

    public function test_descarta_la_notificacion_recien_tras_agotar_todos_los_intentos_sin_tumbar_la_cita(): void
    {
        Mail::fake();
        config()->set('notificaciones.reintentos.intentos', 2);

        // El microservicio de WhatsApp esta caido de forma persistente.
        Http::fake(['*/mensajes' => Http::response(['ok' => false], 503)]);

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'telefono' => '+56922222222']);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'patient_id' => $patient->id,
        ]);

        // Se intento exactamente los 2 intentos configurados, ni uno mas.
        Http::assertSentCount(2);
    }
}
