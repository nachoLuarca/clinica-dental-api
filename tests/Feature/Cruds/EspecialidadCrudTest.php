<?php

namespace Tests\Feature\Cruds;

use App\Models\Especialidad;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

/**
 * CRUD de especialidades (paso 11) y su asignacion a profesionales
 * (Professional puede tener mas de una) y a tratamientos (paso 12: FK real
 * Treatment::especialidad_id).
 */
class EspecialidadCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_puede_crear_especialidad_con_tratamientos(): void
    {
        $tenant = Tenant::factory()->create();
        $treatment = Treatment::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/especialidades', [
                'nombre' => 'Ortodoncia',
                'treatment_ids' => [$treatment->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Ortodoncia')
            ->assertJsonCount(1, 'data.treatments');

        $this->assertDatabaseHas('especialidades', ['nombre' => 'Ortodoncia', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('treatments', ['id' => $treatment->id, 'especialidad_id' => $response->json('data.id')]);
    }

    public function test_nombre_de_especialidad_es_unico_por_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Ortodoncia']);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/especialidades', ['nombre' => 'Ortodoncia'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_staff_puede_listar_y_ver_especialidad(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Endodoncia']);

        $token = $this->staffTokenFor($tenant);

        $this->withToken($token)->getJson('/api/staff/especialidades')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson("/api/staff/especialidades/{$especialidad->id}")
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Endodoncia');
    }

    public function test_staff_puede_actualizar_y_reemplazar_tratamientos(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'General']);
        $prevencion = Treatment::factory()->create(['tenant_id' => $tenant->id, 'especialidad_id' => $especialidad->id]);
        [$restauracion, $estetica] = Treatment::factory(2)->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->putJson("/api/staff/especialidades/{$especialidad->id}", [
                'treatment_ids' => [$restauracion->id, $estetica->id],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.treatments');

        $this->assertDatabaseCount('treatments', 3);
        $this->assertDatabaseHas('treatments', ['id' => $restauracion->id, 'especialidad_id' => $especialidad->id]);
        $this->assertDatabaseHas('treatments', ['id' => $estetica->id, 'especialidad_id' => $especialidad->id]);
        $this->assertDatabaseHas('treatments', ['id' => $prevencion->id, 'especialidad_id' => null]);
    }

    public function test_staff_puede_eliminar_especialidad(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Cirugia']);

        $this->withToken($this->staffTokenFor($tenant))
            ->deleteJson("/api/staff/especialidades/{$especialidad->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('especialidades', ['id' => $especialidad->id]);
    }

    public function test_no_se_puede_asignar_especialidad_de_otro_tenant_a_un_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $otroTenant = Tenant::factory()->create();
        $especialidadAjena = Especialidad::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ortodoncia']);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [
                'nombre' => 'Ana',
                'especialidades' => [$especialidadAjena->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['especialidades.0']);
    }

    public function test_asigna_multiples_especialidades_a_un_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $general = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Odontologia General']);
        $ortodoncia = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Ortodoncia']);

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [
                'nombre' => 'Ana',
                'especialidades' => [$general->id, $ortodoncia->id],
            ]);

        $response->assertCreated()->assertJsonCount(2, 'data.especialidades');

        $profesional = Professional::where('nombre', 'Ana')->firstOrFail();
        $this->assertDatabaseCount('professional_especialidad', 2);
        $this->assertCount(2, $profesional->especialidades);
    }
}
