<?php

namespace Tests\Feature\Notificaciones;

use App\Mail\CitaNotificacionMail;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * Best-effort por canal: aunque WhatsApp este caido, el correo igual sale y,
 * sobre todo, la CITA (fuente de verdad) se crea sin problemas.
 *
 * Aqui NO se fake-ea la cola (driver 'sync' de testing): los jobs corren en
 * linea, asi que si un canal propagara su excepcion, tumbaria la request. Que la
 * request siga en 201 demuestra que el fallo del canal queda contenido.
 */
class NotificacionesBestEffortTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Corremos los jobs en linea para observar el comportamiento best-effort
        // real de cada canal (independiente del driver de cola del entorno).
        config()->set('queue.default', 'sync');
    }

    public function test_whatsapp_caido_no_impide_el_correo_ni_tumba_la_cita(): void
    {
        Mail::fake();
        // El microservicio de WhatsApp responde 503 (sesion caida).
        Http::fake([
            '*/mensajes' => Http::response(['ok' => false], 503),
        ]);

        $tenant = Tenant::factory()->create(['nombre' => 'Clinica Sonrisa']);
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);

        // Paciente con telefono para que el canal WhatsApp intente enviar.
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'telefono' => '+56911111111']);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated();

        // La cita quedo persistida pese al fallo de WhatsApp.
        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
        ]);

        // El correo igual se envio (canal independiente).
        Mail::assertSent(CitaNotificacionMail::class, function (CitaNotificacionMail $mail) use ($patient) {
            return $mail->hasTo($patient->email);
        });

        // Se intento el envio por WhatsApp (el canal no se salteo).
        Http::assertSent(fn ($request) => str_contains($request->url(), '/mensajes'));
    }

    public function test_correo_caido_no_impide_whatsapp_ni_tumba_la_cita(): void
    {
        // Forzamos un mailer inexistente: el canal correo lanzara al resolverlo.
        // WhatsApp responde ok. El job de correo debe capturar el error sin que
        // afecte a WhatsApp ni a la cita.
        config()->set('notificaciones.correo.mailer', 'mailer_inexistente');
        Http::fake([
            '*/mensajes' => Http::response(['ok' => true], 200),
        ]);

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

        // WhatsApp igual recibio el mensaje pese al fallo del correo.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/mensajes'));
    }
}
