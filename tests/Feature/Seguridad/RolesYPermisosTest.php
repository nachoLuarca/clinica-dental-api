<?php

namespace Tests\Feature\Seguridad;

use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\RoleProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Roles/permisos del staff (paso 9): Spatie Permission con "teams" = tenant_id.
 * Cubre lo que un test end-to-end de HTTP puede probar mejor que uno unitario:
 * aislamiento de roles entre tenants y aplicacion real del middleware
 * 'permission' sobre las rutas.
 */
class RolesYPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_puede_borrar_un_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/api/staff/professionals/{$professional->id}")
            ->assertNoContent();
    }

    public function test_recepcion_no_puede_borrar_un_profesional(): void
    {
        $tenant = Tenant::factory()->create();
        $recepcion = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
        $token = $recepcion->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->deleteJson("/api/staff/professionals/{$professional->id}")
            ->assertForbidden()
            // Sin interceptar la excepcion de Spatie, esto respondia en ingles
            // ("User does not have the right permissions") y con un stack
            // trace completo filtrado en el body (APP_DEBUG=true en dev).
            ->assertExactJson([
                'message' => 'No tienes permiso para realizar esta accion.',
                'error' => 'permiso_denegado',
            ]);
    }

    public function test_recepcion_si_puede_listar_pacientes(): void
    {
        $tenant = Tenant::factory()->create();
        $recepcion = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $token = $recepcion->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/patients')
            ->assertOk();
    }

    public function test_registro_publico_no_asigna_ningun_rol(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'clinica-registro-test']);

        $response = $this->postJson('/api/staff/register', [
            'clinica' => $tenant->slug,
            'name' => 'Nuevo Staff',
            'email' => 'nuevo@clinica-registro-test.cl',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.roles', []);

        $token = $response->json('token');
        $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);

        // Sin rol asignado (paso 10, roles editables: no hay ninguno "seguro"
        // que asumir de antemano), el auto-registro publico nunca otorga
        // ningun permiso por default. Un admin de la clinica asigna el rol
        // despues via PATCH /users/{id}/rol.
        $this->withToken($token)
            ->getJson('/api/staff/professionals')
            ->assertForbidden();
    }

    public function test_un_rol_de_un_tenant_no_autoriza_en_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        // Staff de la clinica B, con rol 'admin' -pero de la clinica B-.
        $staffB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $tokenB = $staffB->createToken('staff', ['staff'])->plainTextToken;

        $professionalDeA = Professional::factory()->create(['tenant_id' => $tenantA->id]);

        // El profesional de A ni siquiera es visible para el guard 'tenant'
        // (TenantScope), asi que el intento de borrado con token de B da 404,
        // no 403: la separacion por tenant corta antes que el chequeo de rol.
        $this->withToken($tokenB)
            ->deleteJson("/api/staff/professionals/{$professionalDeA->id}")
            ->assertNotFound();
    }

    public function test_roles_de_dos_tenants_distintos_no_comparten_permisos_editados(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $provisioner = app(RoleProvisioner::class);

        $provisioner->provisionarTenant($tenantA);
        $provisioner->provisionarTenant($tenantB);

        // Role NO tiene global scope propio en este paquete: el tenant_id es
        // una columna plana como cualquier otra, hay que filtrar por ella.
        $rolAdminA = Role::where('name', 'admin')
            ->where('tenant_id', $tenantA->id)
            ->first();
        $rolAdminB = Role::where('name', 'admin')
            ->where('tenant_id', $tenantB->id)
            ->first();

        $this->assertNotNull($rolAdminA);
        $this->assertNotNull($rolAdminB);
        $this->assertNotSame($rolAdminA->id, $rolAdminB->id);
    }
}
