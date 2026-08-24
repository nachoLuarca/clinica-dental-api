<?php

namespace Tests\Feature\Cruds;

use App\Models\Convenio;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class ConvenioCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_staff_puede_listar_convenios_paginados(): void
    {
        $tenant = Tenant::factory()->create();
        Convenio::create(['tenant_id' => $tenant->id, 'nombre' => 'Fonasa', 'tipo' => 'fonasa']);

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/convenios')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total'], 'links'])
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_puede_crear_convenio_con_logo(): void
    {
        $tenant = Tenant::factory()->create();
        $logo = UploadedFile::fake()->create('colmena.png', 10, 'image/png');

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->post('/api/staff/convenios', [
                'nombre' => 'Isapre Colmena',
                'tipo' => 'isapre',
                'descripcion' => 'Bonificacion directa en consulta.',
                'logo' => $logo,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Isapre Colmena')
            ->assertJsonPath('data.tipo', 'isapre');

        $logoPath = $response->json('data.logo_path');
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
        $this->assertDatabaseHas('convenios', ['nombre' => 'Isapre Colmena', 'tenant_id' => $tenant->id]);
    }

    public function test_rechaza_un_tipo_de_convenio_invalido(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/convenios', [
                'nombre' => 'Convenio X',
                'tipo' => 'inventado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tipo']);
    }

    public function test_reemplazar_el_logo_borra_el_anterior(): void
    {
        $tenant = Tenant::factory()->create();
        $convenio = Convenio::create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Caja Los Andes',
            'tipo' => 'caja_compensacion',
            'logo_path' => 'convenios/viejo.png',
        ]);
        Storage::disk('public')->put('convenios/viejo.png', 'contenido');

        $nuevo = UploadedFile::fake()->create('nuevo.png', 10, 'image/png');

        $this->withToken($this->staffTokenFor($tenant))
            ->post("/api/staff/convenios/{$convenio->id}", ['_method' => 'PATCH', 'logo' => $nuevo])
            ->assertOk();

        Storage::disk('public')->assertMissing('convenios/viejo.png');
    }

    public function test_eliminar_un_convenio_borra_su_logo(): void
    {
        $tenant = Tenant::factory()->create();
        $convenio = Convenio::create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Fonasa',
            'tipo' => 'fonasa',
            'logo_path' => 'convenios/fonasa.png',
        ]);
        Storage::disk('public')->put('convenios/fonasa.png', 'contenido');

        $this->withToken($this->staffTokenFor($tenant))
            ->deleteJson("/api/staff/convenios/{$convenio->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing('convenios/fonasa.png');
        $this->assertDatabaseMissing('convenios', ['id' => $convenio->id]);
    }

    public function test_no_ve_convenios_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create();
        Convenio::create(['tenant_id' => $otroTenant->id, 'nombre' => 'Ajeno', 'tipo' => 'fonasa']);

        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->getJson('/api/staff/convenios')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
