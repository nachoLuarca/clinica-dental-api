<?php

namespace Tests\Feature\Reservas;

use App\Models\Sucursal;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * appointments.sucursal_id: snapshot de la sede del profesional al momento
 * de reservar (paso 1 de "sucursales"). Sin paso nuevo en el wizard: queda
 * determinada por el profesional elegido (o auto-asignado).
 */
class SucursalEnCitaTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_la_cita_hereda_la_sucursal_del_profesional_elegido(): void
    {
        $tenant = Tenant::factory()->create();
        $sucursal = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Centro']);
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $prof->update(['sucursal_id' => $sucursal->id]);
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        $response = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        $response->assertJsonPath('data.sucursal_id', $sucursal->id);
        $this->assertDatabaseHas('appointments', [
            'professional_id' => $prof->id,
            'sucursal_id' => $sucursal->id,
        ]);
    }

    public function test_sin_sucursal_asignada_al_profesional_la_cita_queda_sin_sucursal(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        $response = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        $response->assertJsonPath('data.sucursal_id', null);
    }

    public function test_en_modo_cualquier_profesional_la_cita_hereda_la_sucursal_del_auto_asignado(): void
    {
        $tenant = Tenant::factory()->create();
        $sucursal = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Norte']);
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $prof->update(['sucursal_id' => $sucursal->id]);
        $treatment = $this->tratamiento($tenant, 60);
        [, $token] = $this->pacienteConToken($tenant);

        $response = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        $response->assertJsonPath('data.professional_id', $prof->id);
        $response->assertJsonPath('data.sucursal_id', $sucursal->id);
    }
}
