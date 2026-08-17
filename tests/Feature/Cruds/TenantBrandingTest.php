<?php

namespace Tests\Feature\Cruds;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Marca de la propia clinica (nombre/logo/color): GET/PATCH /api/staff/tenant.
 * Sin {tenant} en la ruta -siempre la del usuario autenticado-, y PATCH
 * exclusivo de 'admin' (paso 9, ver README seccion Roles).
 */
class TenantBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('public');
    }

    public function test_staff_ve_los_datos_de_marca_de_su_clinica(): void
    {
        $tenant = Tenant::factory()->create(['nombre' => 'Clinica Uno']);
        $staff = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/tenant')
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Clinica Uno')
            ->assertJsonPath('data.logo_url', null);
    }

    public function test_admin_puede_editar_nombre_color_y_subir_logo(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;
        // create() en vez de image(): el contenedor no tiene la extension GD,
        // y create() no la necesita (declara el mime sin generar pixeles).
        $logo = UploadedFile::fake()->create('logo.png', 10, 'image/png');

        $response = $this->withToken($token)
            ->post('/api/staff/tenant', [
                '_method' => 'PATCH',
                'nombre' => 'Clinica Renombrada',
                'color_primario' => '#1a73e8',
                'logo' => $logo,
            ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Clinica Renombrada')
            ->assertJsonPath('data.color_primario', '#1a73e8');

        $logoPath = $response->json('data.logo_path');
        $this->assertNotNull($logoPath);
        Storage::disk('public')->assertExists($logoPath);
    }

    public function test_recepcion_no_puede_editar_la_marca_de_la_clinica(): void
    {
        $tenant = Tenant::factory()->create(['nombre' => 'Original']);
        $recepcion = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $token = $recepcion->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->post('/api/staff/tenant', ['_method' => 'PATCH', 'nombre' => 'Hackeada'])
            ->assertForbidden();

        $this->assertSame('Original', $tenant->fresh()->nombre);
    }

    public function test_no_puede_editar_la_marca_de_otra_clinica(): void
    {
        $tenantA = Tenant::factory()->create(['nombre' => 'Clinica A']);
        $tenantB = Tenant::factory()->create(['nombre' => 'Clinica B']);
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $token = $adminB->createToken('staff', ['staff'])->plainTextToken;

        // El endpoint no acepta id de tenant: el PATCH de un staff de B solo
        // puede tocar la clinica de B, nunca la de A.
        $this->withToken($token)
            ->post('/api/staff/tenant', ['_method' => 'PATCH', 'nombre' => 'Hackeada'])
            ->assertOk();

        $this->assertSame('Clinica A', $tenantA->fresh()->nombre);
        $this->assertSame('Hackeada', $tenantB->fresh()->nombre);
    }
}
