<?php

namespace Tests\Feature\Reservas;

use App\Models\Sucursal;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithReservas;
use Tests\TestCase;

/**
 * Filtro de reserva por sucursal (wizard estilo Dentalink con entry point
 * Sucursal): el listado publico de profesionales y la disponibilidad
 * agregada "cualquier profesional" deben poder acotarse a una sede via
 * ?sucursal_id=, ademas (o en vez) del filtro por especialidad ya existente.
 */
class FiltroSucursalTest extends TestCase
{
    use InteractsWithReservas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_listado_publico_sin_sucursal_id_no_filtra(): void
    {
        $tenant = Tenant::factory()->create();
        $sedeCentro = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Centro']);
        $sedeNorte = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Norte']);
        $this->profesionalConHorario($tenant, 1, '09:00', '17:00')->update(['sucursal_id' => $sedeCentro->id]);
        $this->profesionalConHorario($tenant, 1, '09:00', '17:00')->update(['sucursal_id' => $sedeNorte->id]);

        $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/profesionales')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_listado_publico_con_sucursal_id_filtra_por_sede(): void
    {
        $tenant = Tenant::factory()->create();
        $sedeCentro = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Centro']);
        $sedeNorte = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Norte']);
        $profCentro = $this->profesionalConHorario($tenant, 1, '09:00', '17:00');
        $profCentro->update(['sucursal_id' => $sedeCentro->id]);
        $this->profesionalConHorario($tenant, 1, '09:00', '17:00')->update(['sucursal_id' => $sedeNorte->id]);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/profesionales?sucursal_id={$sedeCentro->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.id', $profCentro->id);
        $response->assertJsonPath('data.0.sucursal_id', $sedeCentro->id);
        $response->assertJsonPath('data.0.sucursal.nombre', 'Sede Centro');
    }

    public function test_listado_publico_expone_sucursal_null_si_no_tiene_sede(): void
    {
        $tenant = Tenant::factory()->create();
        $this->profesionalConHorario($tenant, 1, '09:00', '17:00');

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson('/api/publico/profesionales')
            ->assertOk();

        $response->assertJsonPath('data.0.sucursal_id', null);
        $response->assertJsonPath('data.0.sucursal', null);
    }

    public function test_disponibilidad_cualquier_profesional_con_sucursal_id_solo_agrega_esa_sede(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);

        $sedeCentro = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Centro']);
        $sedeNorte = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Norte']);
        $profCentro = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $profCentro->update(['sucursal_id' => $sedeCentro->id]);
        $profNorte = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $profNorte->update(['sucursal_id' => $sedeNorte->id]);

        $treatment = Treatment::factory()->create(['tenant_id' => $tenant->id, 'duracion_minutos' => 60]);
        [, $token] = $this->pacienteConToken($tenant);

        $response = $this->withToken($token)
            ->getJson("/api/paciente/availability?treatment_id={$treatment->id}&fecha={$fecha->toDateString()}&sucursal_id={$sedeCentro->id}")
            ->assertOk();

        $professionalIds = collect($response->json('data.slots'))->pluck('professional_id')->unique();

        $this->assertTrue($professionalIds->contains($profCentro->id));
        $this->assertFalse($professionalIds->contains($profNorte->id));
    }

    public function test_disponibilidad_publica_sin_login_tambien_acepta_sucursal_id(): void
    {
        $tenant = Tenant::factory()->create();
        $fecha = $this->proximaFechaEnDiaSemana(1);

        $sedeCentro = Sucursal::create(['tenant_id' => $tenant->id, 'nombre' => 'Sede Centro']);
        $profCentro = $this->profesionalConHorario($tenant, 1, '09:00', '11:00');
        $profCentro->update(['sucursal_id' => $sedeCentro->id]);

        $treatment = Treatment::factory()->create(['tenant_id' => $tenant->id, 'duracion_minutos' => 60]);

        $response = $this->withHeaders(['X-Clinica' => $tenant->slug])
            ->getJson("/api/publico/availability?treatment_id={$treatment->id}&fecha={$fecha->toDateString()}&sucursal_id={$sedeCentro->id}")
            ->assertOk();

        $professionalIds = collect($response->json('data.slots'))->pluck('professional_id')->unique();

        $this->assertTrue($professionalIds->contains($profCentro->id));
    }
}
