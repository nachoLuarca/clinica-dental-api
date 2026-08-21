<?php

namespace Tests\Feature\Publico;

use App\Models\Especialidad;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Catalogo publico de especialidades (sin login): especialidad -> sus
 * tratamientos activos + cantidad de profesionales activos vinculados, ya
 * armado por el backend en una sola query.
 */
class EspecialidadesCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_lista_especialidades_con_tratamientos_activos_y_conteo_de_profesionales(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Endodoncia']);

        $tratamiento = Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'nombre' => 'Tratamiento de conducto',
            'activo' => true,
        ]);

        $prof1 = Professional::factory()->create(['tenant_id' => $tenant->id, 'activo' => true]);
        $prof2 = Professional::factory()->create(['tenant_id' => $tenant->id, 'activo' => true]);
        $especialidad->professionals()->attach([$prof1->id, $prof2->id]);

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.nombre', 'Endodoncia');
        $response->assertJsonPath('data.0.profesionales_count', 2);
        $response->assertJsonCount(1, 'data.0.tratamientos');
        $response->assertJsonPath('data.0.tratamientos.0.id', $tratamiento->id);
    }

    public function test_no_cuenta_profesionales_inactivos(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Ortodoncia']);

        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'activo' => true,
        ]);

        $activo = Professional::factory()->create(['tenant_id' => $tenant->id, 'activo' => true]);
        $inactivo = Professional::factory()->create(['tenant_id' => $tenant->id, 'activo' => false]);
        $especialidad->professionals()->attach([$activo->id, $inactivo->id]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk()
            ->assertJsonPath('data.0.profesionales_count', 1);
    }

    public function test_incluye_especialidad_sin_profesionales_si_tiene_tratamientos_activos(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Implantologia']);

        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'activo' => true,
        ]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.profesionales_count', 0);
    }

    public function test_no_incluye_tratamientos_inactivos_de_una_especialidad_listada(): void
    {
        $tenant = Tenant::factory()->create();
        $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Odontopediatria']);

        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'activo' => true,
        ]);
        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $especialidad->id,
            'activo' => false,
        ]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk()
            ->assertJsonCount(1, 'data.0.tratamientos');
    }

    public function test_no_incluye_especialidades_sin_ningun_tratamiento_activo(): void
    {
        $tenant = Tenant::factory()->create();
        $sinTratamientos = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Cirugia Maxilofacial']);
        $soloInactivos = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => 'Rehabilitacion Oral']);

        Treatment::factory()->create([
            'tenant_id' => $tenant->id,
            'especialidad_id' => $soloInactivos->id,
            'activo' => false,
        ]);

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_no_muestra_especialidades_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        $especialidadAjena = Especialidad::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Endodoncia']);
        Treatment::factory()->create([
            'tenant_id' => $otroTenant->id,
            'especialidad_id' => $especialidadAjena->id,
            'activo' => true,
        ]);

        $tenant = Tenant::factory()->create();

        $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_ordena_alfabeticamente_por_nombre(): void
    {
        $tenant = Tenant::factory()->create();

        foreach (['Ortodoncia', 'Endodoncia', 'Implantologia'] as $nombre) {
            $especialidad = Especialidad::create(['tenant_id' => $tenant->id, 'nombre' => $nombre]);
            Treatment::factory()->create([
                'tenant_id' => $tenant->id,
                'especialidad_id' => $especialidad->id,
                'activo' => true,
            ]);
        }

        $response = $this->withHeader('X-Clinica', $tenant->slug)
            ->getJson('/api/publico/especialidades')
            ->assertOk();

        $this->assertSame(
            ['Endodoncia', 'Implantologia', 'Ortodoncia'],
            $response->json('data.*.nombre'),
        );
    }
}
