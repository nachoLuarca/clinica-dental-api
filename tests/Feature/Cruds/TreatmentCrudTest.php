<?php

namespace Tests\Feature\Cruds;

use App\Models\Tenant;
use App\Models\Treatment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class TreatmentCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_puede_crear_tratamiento(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/treatments', [
                'nombre' => 'Limpieza',
                'descripcion' => 'Profilaxis',
                'precio' => 25000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Limpieza');

        $this->assertDatabaseHas('treatments', ['nombre' => 'Limpieza', 'tenant_id' => $tenant->id]);
    }

    public function test_staff_puede_crear_tratamiento_diferencial(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/treatments', [
                'nombre' => 'Atencion especial no listada',
                'precio' => 99000,
                'es_diferencial' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.es_diferencial', true);
    }

    public function test_staff_puede_listar_actualizar_y_eliminar_tratamiento(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->staffTokenFor($tenant);
        Treatment::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $t = Treatment::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Old']);

        $this->withToken($token)->getJson('/api/staff/treatments')
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonStructure(['data', 'meta', 'links']);

        $this->withToken($token)->putJson("/api/staff/treatments/{$t->id}", ['nombre' => 'Nuevo', 'precio' => 30000])
            ->assertOk()->assertJsonPath('data.nombre', 'Nuevo');

        $this->withToken($token)->deleteJson("/api/staff/treatments/{$t->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('treatments', ['id' => $t->id]);
    }

    public function test_crear_tratamiento_valida_precio_y_nombre(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/treatments', ['precio' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'precio']);
    }
}
