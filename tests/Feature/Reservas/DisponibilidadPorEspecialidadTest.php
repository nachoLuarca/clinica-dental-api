<?php

namespace Tests\Feature\Reservas;

use App\Models\Especialidad;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * Disponibilidad pedida por especialidad_id, sin tratamiento puntual todavia
 * (wizard estilo Dentalink: entry points Especialidad/Profesional/Sucursal
 * muestran horarios antes de que el paciente elija un tratamiento exacto; el
 * treatment_id real recien se fija en el paso Confirmar, que ya lo exige via
 * POST /publico/citas).
 *
 * La duracion de los slots generados es la del tratamiento activo mas largo
 * de la especialidad (ver AvailabilityService::forTenantPorEspecialidad):
 * garantiza que cualquier tratamiento que se elija despues entre en el
 * horario mostrado.
 */
class DisponibilidadPorEspecialidadTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function especialidad(Tenant $tenant, string $nombre): Especialidad
    {
        return Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => $nombre]);
    }

    public function test_disponibilidad_por_especialidad_usa_la_duracion_mas_larga_de_sus_tratamientos_activos(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $especialidad = $this->especialidad($tenant, 'Ortodoncia');
        $especialidad->professionals()->attach($prof);

        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'duracion_minutos' => 30,
            'activo' => true,
        ]);
        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'duracion_minutos' => 60,
            'activo' => true,
        ]);
        // Inactivo, mas largo que los activos: no debe influir.
        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'duracion_minutos' => 120,
            'activo' => false,
        ]);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?especialidad_id={$especialidad->id}&fecha={$fecha->toDateString()}")
            ->assertOk();

        $response->assertJsonPath('data.duracion_minutos', 60);
        $response->assertJsonPath('data.especialidad_id', $especialidad->id);
        $response->assertJsonPath('data.treatment_id', null);
        // 09:00-11:00 con slots de 60 min => 09:00 y 10:00.
        $response->assertJsonCount(2, 'data.slots');
    }

    public function test_especialidad_sin_tratamientos_activos_no_tiene_duracion_de_referencia(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $especialidad = $this->especialidad($tenant, 'Ortodoncia');
        $especialidad->professionals()->attach($prof);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?especialidad_id={$especialidad->id}&fecha={$fecha->toDateString()}")
            ->assertOk();

        $response->assertJsonPath('data.duracion_minutos', null);
        $response->assertJsonCount(0, 'data.slots');
    }

    public function test_especialidad_id_solo_agrega_profesionales_de_esa_especialidad(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $ortodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $endodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $especialidadOrto = $this->especialidad($tenant, 'Ortodoncia');
        $especialidadOrto->professionals()->attach($ortodoncista);
        $this->especialidad($tenant, 'Endodoncia')->professionals()->attach($endodoncista);

        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidadOrto->id,
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?especialidad_id={$especialidadOrto->id}&fecha={$fecha->toDateString()}")
            ->assertOk();

        $professionalIds = collect($response->json('data.slots'))->pluck('professional_id')->unique();

        $this->assertTrue($professionalIds->contains($ortodoncista->id));
        $this->assertFalse($professionalIds->contains($endodoncista->id));
    }

    public function test_treatment_id_y_especialidad_id_juntos_es_invalido(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = $this->especialidad($tenant, 'Ortodoncia');
        $treatment = Treatment::factory()->create(['tenant_id' => $tenant->id]);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?treatment_id={$treatment->id}&especialidad_id={$especialidad->id}&fecha=2030-01-07")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['especialidad_id']);
    }

    public function test_sin_treatment_id_ni_especialidad_id_es_invalido(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/availability?fecha=2030-01-07')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['treatment_id']);
    }

    public function test_con_professional_id_especialidad_id_no_reemplaza_a_treatment_id(): void
    {
        $tenant = Tenant::factory()->create();
        $prof = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $especialidad = $this->especialidad($tenant, 'Ortodoncia');

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?professional_id={$prof->id}&especialidad_id={$especialidad->id}&fecha=2030-01-07")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['treatment_id']);
    }

    public function test_disponibilidad_por_especialidad_combina_con_sucursal_id(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);
        $especialidad = $this->especialidad($tenant, 'Ortodoncia');
        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'duracion_minutos' => 60,
            'activo' => true,
        ]);

        $sedeCentro = \App\Models\Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Centro']);
        $sedeNorte = \App\Models\Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Norte']);

        $profCentro = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $profCentro->update(['sucursal_id' => $sedeCentro->id]);
        $especialidad->professionals()->attach($profCentro);

        $profNorte = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $profNorte->update(['sucursal_id' => $sedeNorte->id]);
        $especialidad->professionals()->attach($profNorte);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?especialidad_id={$especialidad->id}&sucursal_id={$sedeCentro->id}&fecha={$fecha->toDateString()}")
            ->assertOk();

        $professionalIds = collect($response->json('data.slots'))->pluck('professional_id')->unique();

        $this->assertTrue($professionalIds->contains($profCentro->id));
        $this->assertFalse($professionalIds->contains($profNorte->id));
    }
}
