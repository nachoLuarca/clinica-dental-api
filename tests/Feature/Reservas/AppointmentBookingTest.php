<?php

namespace Tests\Feature\Reservas;

use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use InteractsWithReservas, InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Evita arrastre de cache de disponibilidad entre tests.
        Cache::flush();
    }

    public function test_paciente_reserva_una_cita_para_si_mismo(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient, $token] = $this->pacienteConToken($tenant);

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'reservada')
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.duracion_minutos', 60);

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_paciente_no_puede_reservar_para_otro_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient, $token] = $this->pacienteConToken($tenant);
        [$otro] = $this->pacienteConToken($tenant);

        // Aunque mande patient_id de otro, la cita queda para el autenticado.
        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'patient_id' => $otro->id,
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id);
    }

    public function test_bloqueo_optimista_dos_reservas_mismo_slot_solo_una_gana(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token1] = $this->pacienteConToken($tenant);
        [, $token2] = $this->pacienteConToken($tenant);

        $slot = $fecha->copy()->setTime(9, 0)->toDateTimeString();

        $primera = $this->withToken($token1)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $slot,
        ]);
        $primera->assertCreated();

        $segunda = $this->withToken($token2)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $slot,
        ]);

        // El segundo intento recibe un error CLARO (409), no un 500 generico.
        $segunda->assertStatus(409)
            ->assertJsonPath('error', 'slot_no_disponible');

        // Solo una cita activa quedo en el mismo slot.
        $this->assertSame(1, Appointment::query()
            ->where('professional_id', $prof->id)
            ->where('fecha_hora', $fecha->copy()->setTime(9, 0))
            ->where('estado', '!=', Appointment::ESTADO_CANCELADA)
            ->count());
    }

    public function test_indice_unico_parcial_rechaza_duplicado_a_nivel_bd(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);

        // Insercion directa del MISMO slot activo: la BD debe rechazarla.
        $this->expectException(QueryException::class);

        Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id,
            'professional_id' => $prof->id,
            'patient_id' => $patient->id,
            'treatment_id' => $treatment->id,
        ]);
    }

    public function test_slot_liberado_tras_cancelar_puede_reservarse_de_nuevo(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        $slot = $fecha->copy()->setTime(9, 0)->toDateTimeString();

        $primera = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $slot,
        ])->assertCreated();

        $id = $primera->json('data.id');

        $this->withToken($token)->deleteJson("/api/paciente/appointments/{$id}")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');

        // El mismo slot vuelve a estar libre.
        $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $slot,
        ])->assertCreated();
    }

    public function test_no_se_puede_reservar_fuera_del_horario(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        // 12:00 esta fuera de 09:00-11:00.
        $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(12, 0)->toDateTimeString(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_hora']);
    }

    public function test_staff_reserva_para_un_paciente_de_su_clinica(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/appointments', [
                'patient_id' => $patient->id,
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id);
    }

    public function test_paciente_solo_lista_sus_propias_citas(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient, $token] = $this->pacienteConToken($tenant);
        [$otro] = $this->pacienteConToken($tenant);

        Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id, 'professional_id' => $prof->id,
            'patient_id' => $patient->id, 'treatment_id' => $treatment->id,
        ]);
        Appointment::factory()->at($fecha->copy()->setTime(10, 0), 60)->create([
            'tenant_id' => $tenant->id, 'professional_id' => $prof->id,
            'patient_id' => $otro->id, 'treatment_id' => $treatment->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/paciente/appointments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.patient_id', $patient->id);
    }

    public function test_paciente_no_puede_cancelar_cita_de_otro(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);
        [$otro] = $this->pacienteConToken($tenant);

        $citaAjena = Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id, 'professional_id' => $prof->id,
            'patient_id' => $otro->id, 'treatment_id' => $treatment->id,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/paciente/appointments/{$citaAjena->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('appointments', [
            'id' => $citaAjena->id,
            'estado' => 'reservada',
        ]);
    }

    public function test_staff_no_reserva_para_paciente_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenantA, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenantA, 60);
        [$patientB] = $this->pacienteConToken($tenantB);

        $this->withToken($this->staffTokenFor($tenantA))
            ->postJson('/api/staff/appointments', [
                'patient_id' => $patientB->id,
                'professional_id' => $prof->id,
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertNotFound();
    }

    public function test_paciente_no_accede_a_endpoints_de_staff(): void
    {
        $tenant = Tenant::factory()->create();
        [, $token] = $this->pacienteConToken($tenant);

        $this->withToken($token)
            ->getJson('/api/staff/appointments')
            ->assertUnauthorized();
    }
}
