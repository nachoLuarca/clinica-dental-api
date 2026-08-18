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
 * Filtro de reserva por especialidad<->categoria del tratamiento (paso 11):
 * el listado publico de profesionales, la disponibilidad agregada
 * "cualquier profesional" y el auto-asignado al reservar deben acotarse a
 * los profesionales cuya especialidad cubre la categoria del tratamiento.
 */
class FiltroEspecialidadTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function especialidad(Tenant $tenant, string $nombre, array $categorias): Especialidad
    {
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => $nombre]);
        foreach ($categorias as $categoria) {
            $especialidad->categorias()->create(['categoria' => $categoria]);
        }

        return $especialidad;
    }

    public function test_listado_publico_sin_treatment_id_no_filtra(): void
    {
        $tenant = Tenant::factory()->create();
        $ortodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $endodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $this->especialidad($tenant, 'Ortodoncia', ['Ortodoncia'])->professionals()->attach($ortodoncista);
        $this->especialidad($tenant, 'Endodoncia', ['Endodoncia'])->professionals()->attach($endodoncista);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/profesionales')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_listado_publico_con_treatment_id_filtra_por_categoria(): void
    {
        $tenant = Tenant::factory()->create();
        $ortodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $endodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $this->especialidad($tenant, 'Ortodoncia', ['Ortodoncia'])->professionals()->attach($ortodoncista);
        $this->especialidad($tenant, 'Endodoncia', ['Endodoncia'])->professionals()->attach($endodoncista);

        $tratamientoOrto = Treatment::factory()->create(['tenant_id' => $tenant->id, 'categoria' => 'Ortodoncia']);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/profesionales?treatment_id={$tratamientoOrto->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertSame($ortodoncista->id, $response->json('data.0.id'));
    }

    public function test_disponibilidad_cualquier_profesional_solo_agrega_al_elegible(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);

        $ortodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $endodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $this->especialidad($tenant, 'Ortodoncia', ['Ortodoncia'])->professionals()->attach($ortodoncista);
        $this->especialidad($tenant, 'Endodoncia', ['Endodoncia'])->professionals()->attach($endodoncista);

        $tratamientoOrto = Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'categoria' => 'Ortodoncia',
            'duracion_minutos' => 60,
        ]);

        [, $token] = $this->pacienteConToken($tenant);

        $response = $this->withToken($token)
            ->getJson("/api/paciente/availability?treatment_id={$tratamientoOrto->id}&fecha={$fecha->toDateString()}")
            ->assertOk();

        $professionalIds = collect($response->json('data.slots'))->pluck('professional_id')->unique();

        $this->assertTrue($professionalIds->contains($ortodoncista->id));
        $this->assertFalse($professionalIds->contains($endodoncista->id));
    }

    public function test_reserva_cualquier_profesional_solo_auto_asigna_al_elegible(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);

        $ortodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $endodoncista = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $this->especialidad($tenant, 'Ortodoncia', ['Ortodoncia'])->professionals()->attach($ortodoncista);
        $this->especialidad($tenant, 'Endodoncia', ['Endodoncia'])->professionals()->attach($endodoncista);

        $tratamientoOrto = Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'categoria' => 'Ortodoncia',
            'duracion_minutos' => 60,
        ]);

        [, $token] = $this->pacienteConToken($tenant);

        $response = $this->withToken($token)->postJson('/api/paciente/appointments', [
            'treatment_id' => $tratamientoOrto->id,
            'fecha_hora' => $fecha->copy()->setTime(9, 0)->toDateTimeString(),
        ])->assertCreated();

        $response->assertJsonPath('data.professional_id', $ortodoncista->id);
    }
}
