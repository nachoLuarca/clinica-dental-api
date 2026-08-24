<?php

namespace Tests\Feature\Cruds;

use App\Models\Professional;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class ProfessionalCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_puede_listar_profesionales_paginados(): void
    {
        $tenant = Tenant::factory()->create();
        Professional::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/professionals')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total'], 'links'])
            ->assertJsonCount(3, 'data');
    }

    public function test_staff_puede_crear_profesional_con_horarios(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [
                'nombre' => 'Ana',
                'apellido' => 'Perez',
                'especialidad' => 'Ortodoncia',
                'horarios' => [
                    ['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '13:00'],
                    ['dia_semana' => 3, 'hora_inicio' => '14:00', 'hora_fin' => '18:00'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Ana')
            ->assertJsonCount(2, 'data.schedules');

        $this->assertDatabaseHas('professionals', ['nombre' => 'Ana', 'tenant_id' => $tenant->id]);
        $this->assertDatabaseCount('professional_schedules', 2);
    }

    public function test_horario_con_hora_fin_antes_de_inicio_falla(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [
                'nombre' => 'Ana',
                'horarios' => [
                    ['dia_semana' => 1, 'hora_inicio' => '13:00', 'hora_fin' => '09:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['horarios.0.hora_fin']);
    }

    public function test_staff_puede_ver_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $prof = Professional::factory()->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson("/api/staff/professionals/{$prof->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $prof->id);
    }

    public function test_staff_puede_actualizar_profesional_y_reemplazar_horarios(): void
    {
        $tenant = Tenant::factory()->create();
        $prof = Professional::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Old']);

        // La creacion manual del horario ocurre fuera de un request, por lo que
        // no hay tenant en el TenantContext; lo fijamos temporalmente para que
        // el trait BelongsToTenant asigne bien el tenant_id.
        app(TenantContext::class)->runWithTenant($tenant->id, function () use ($prof) {
            $prof->schedules()->create(['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '13:00']);
        });

        $this->withToken($this->staffTokenFor($tenant))
            ->putJson("/api/staff/professionals/{$prof->id}", [
                'nombre' => 'Nuevo',
                'horarios' => [
                    ['dia_semana' => 5, 'hora_inicio' => '10:00', 'hora_fin' => '12:00'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nuevo')
            ->assertJsonCount(1, 'data.schedules');

        $this->assertDatabaseCount('professional_schedules', 1);
        $this->assertDatabaseHas('professional_schedules', ['professional_id' => $prof->id, 'dia_semana' => 5]);
    }

    public function test_staff_puede_eliminar_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $prof = Professional::factory()->create(['tenant_id' => $tenant->id]);

        $this->withToken($this->staffTokenFor($tenant))
            ->deleteJson("/api/staff/professionals/{$prof->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('professionals', ['id' => $prof->id]);
    }

    public function test_crear_profesional_valida_nombre_requerido(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/professionals', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_staff_puede_crear_profesional_con_foto_bio_y_matricula(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        $foto = UploadedFile::fake()->create('ana.png', 10, 'image/png');

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->post('/api/staff/professionals', [
                'nombre' => 'Ana',
                'bio' => 'Odontologa general con 10 anos de experiencia.',
                'matricula' => '12345-6',
                'foto' => $foto,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.bio', 'Odontologa general con 10 anos de experiencia.')
            ->assertJsonPath('data.matricula', '12345-6');

        $fotoPath = $response->json('data.foto_path');
        $this->assertNotNull($fotoPath);
        Storage::disk('public')->assertExists($fotoPath);
    }

    public function test_reemplazar_la_foto_del_profesional_borra_la_anterior(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        $prof = Professional::factory()->create(['tenant_id' => $tenant->id, 'foto_path' => 'profesionales/vieja.png']);
        Storage::disk('public')->put('profesionales/vieja.png', 'contenido');

        $nueva = UploadedFile::fake()->create('nueva.png', 10, 'image/png');

        $this->withToken($this->staffTokenFor($tenant))
            ->post("/api/staff/professionals/{$prof->id}", ['_method' => 'PATCH', 'foto' => $nueva])
            ->assertOk();

        Storage::disk('public')->assertMissing('profesionales/vieja.png');
    }
}
