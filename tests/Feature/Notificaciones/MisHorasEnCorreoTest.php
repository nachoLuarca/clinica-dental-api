<?php

namespace Tests\Feature\Notificaciones;

use App\Mail\CitaNotificacionMail;
use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * El correo de confirmacion debe explicar como cancelar sin login (RUT +
 * fecha de nacimiento en "Mis horas") -antes solo lo hacia el de cancelacion-.
 */
class MisHorasEnCorreoTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('queue.default', 'sync');
    }

    public function test_correo_de_confirmacion_explica_como_cancelar_sin_login(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        Mail::assertSent(
            CitaNotificacionMail::class,
            fn (CitaNotificacionMail $mail) => str_contains($mail->render(), 'Mis horas')
                && str_contains($mail->render(), 'RUT'),
        );
    }

    public function test_url_de_mis_horas_usa_la_config_cuando_esta_seteada(): void
    {
        config()->set('notificaciones.paciente_frontend_url', 'https://paciente.clinica-demo.test');
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        Mail::assertSent(
            CitaNotificacionMail::class,
            fn (CitaNotificacionMail $mail) => $mail->mensaje->misHorasUrl === 'https://paciente.clinica-demo.test/mis-horas'
                && str_contains($mail->render(), 'https://paciente.clinica-demo.test/mis-horas'),
        );
    }
}
