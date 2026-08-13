<?php

namespace Tests\Feature\Reservas;

use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

/**
 * Verifica que la disponibilidad se cachea y que la cache se invalida POR EVENTO
 * (crear/cancelar cita), no por TTL. El store de cache en tests es 'array', que
 * soporta tags igual que redis en desarrollo.
 */
class AvailabilityCacheTest extends TestCase
{
    use InteractsWithReservas, InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Con :memory: los ids reinician a 1 en cada test, por lo que la clave de
        // cache (tenant+prof+fecha+duracion) coincide entre tests. El store array
        // persiste entre tests, asi que lo limpiamos para no arrastrar resultados.
        Cache::flush();
    }

    public function test_la_disponibilidad_se_cachea(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [$patient] = $this->pacienteConToken($tenant);
        $token = $this->staffTokenFor($tenant);
        $url = "/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}";

        // Primera consulta: 2 slots, queda cacheada.
        $this->withToken($token)->getJson($url)->assertJsonCount(2, 'data.slots');

        // Inserto una cita DIRECTO en BD (sin pasar por el servicio: no invalida).
        Appointment::factory()->at($fecha->copy()->setTime(9, 0), 60)->create([
            'tenant_id' => $tenant->id, 'professional_id' => $prof->id,
            'patient_id' => $patient->id, 'treatment_id' => $treatment->id,
        ]);

        // Sigue devolviendo 2: prueba que respondio desde cache (dato viejo).
        $this->withToken($token)->getJson($url)->assertJsonCount(2, 'data.slots');
    }

    public function test_crear_cita_invalida_la_cache(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $pToken] = $this->pacienteConToken($tenant);
        $sToken = $this->staffTokenFor($tenant);
        $url = "/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}";

        $this->withToken($sToken)->getJson($url)->assertJsonCount(2, 'data.slots');

        // Crear cita via API invalida la cache del profesional/fecha.
        $this->withToken($pToken)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        // Ahora refleja el slot ocupado: queda 1.
        $this->withToken($sToken)->getJson($url)
            ->assertJsonCount(1, 'data.slots')
            ->assertJsonPath('data.slots.0.inicio', '10:00');
    }

    public function test_cancelar_cita_invalida_la_cache(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $treatment = $this->tratamiento($tenant, 60);
        [, $pToken] = $this->pacienteConToken($tenant);
        $sToken = $this->staffTokenFor($tenant);
        $url = "/api/staff/availability?professional_id={$prof->id}&treatment_id={$treatment->id}&fecha={$fecha->toDateString()}";

        $cita = $this->withToken($pToken)->postJson('/api/paciente/appointments', [
            'professional_id' => $prof->id,
            'treatment_id' => $treatment->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        // Cachea el estado con 1 slot libre.
        $this->withToken($sToken)->getJson($url)->assertJsonCount(1, 'data.slots');

        // Cancelar via API invalida la cache.
        $this->withToken($pToken)->deleteJson("/api/paciente/appointments/{$cita->json('data.id')}")->assertOk();

        // Vuelven a estar los 2 slots libres.
        $this->withToken($sToken)->getJson($url)->assertJsonCount(2, 'data.slots');
    }
}
