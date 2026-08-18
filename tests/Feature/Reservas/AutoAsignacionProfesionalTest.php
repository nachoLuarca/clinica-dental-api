<?php

namespace Tests\Feature\Reservas;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

/**
 * Modo "cualquier profesional disponible" (paso 11): professional_id
 * opcional en disponibilidad y en la creacion de citas.
 */
class AutoAsignacionProfesionalTest extends TestCase
{
    use InteractsWithReservas, InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_disponibilidad_sin_professional_id_agrega_todos_los_profesionales(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $profA = $this->profesionalConHorario($tenant, 1, '09:00', '10:00');
        $profB = $this->profesionalConHorario($tenant, 1, '09:00', '10:00');
        $treatment = $this->tratamiento($tenant, 60);

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/availability?treatment_id={$treatment->id}&fecha={$fecha->toDateString()}")
            ->assertOk()
            ->assertJsonPath('data.professional_id', null)
            ->assertJsonCount(2, 'data.slots');

        $idsEnSlots = collect($response->json('data.slots'))->pluck('professional_id')->sort()->values();
        $this->assertEquals([$profA->id, $profB->id], $idsEnSlots->sort()->values()->all());
    }

    public function test_reserva_sin_professional_id_auto_asigna_uno_disponible(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.professional_id', $prof->id);
    }

    public function test_reserva_sin_professional_id_salta_al_siguiente_si_el_primero_esta_ocupado(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $profOcupado = $this->profesionalConHorario($tenant, 1, '09:00', '10:00');
        $profLibre = $this->profesionalConHorario($tenant, 1, '09:00', '10:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $tokenDueno] = $this->pacienteConToken($tenant);
        [, $token] = $this->pacienteConToken($tenant);

        // Ocupa al primer profesional creado en ese horario.
        $this->withToken($tokenDueno)->postJson('/api/paciente/appointments', [
            'professional_id' => $profOcupado->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        // La siguiente reserva sin professional_id debe caer en el que sigue
        // libre, no fallar con 409 solo porque el primer candidato esta ocupado.
        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.professional_id', $profLibre->id);
    }

    public function test_reserva_sin_professional_id_falla_si_nadie_tiene_el_horario_libre(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $treatment = $this->tratamiento($tenant, 60);
        // Ningun profesional con horario para ese dia.
        [, $token] = $this->pacienteConToken($tenant);

        $this->withToken($token)
            ->postJson('/api/paciente/appointments', [
                'treatment_id' => $treatment->id,
                'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
            ])
            ->assertStatus(409);
    }

    public function test_listado_publico_de_profesionales(): void
    {
        $tenant = Tenant::factory()->create();
        $this->profesionalConHorario($tenant, 1, '09:00', '10:00');
        $this->profesionalConHorario($tenant, 2, '09:00', '10:00');

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/profesionales')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'nombre', 'apellido', 'especialidad']]]);
    }
}
