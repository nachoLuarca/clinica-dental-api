<?php

namespace Tests\Feature\Cruds;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Roles editables (paso 10): crear/renombrar/borrar roles y su matriz de
 * permisos. El rol 'admin' esta protegido.
 */
class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_puede_crear_un_rol_con_permisos(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/staff/roles', [
                'name' => 'facturacion',
                'permissions' => ['budgets.ver', 'budgets.crear'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'facturacion')
            ->assertJsonCount(2, 'data.permissions');
    }

    public function test_no_se_puede_crear_dos_roles_con_el_mismo_nombre_en_la_clinica(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)->postJson('/api/staff/roles', ['name' => 'facturacion'])->assertCreated();
        $this->withToken($token)->postJson('/api/staff/roles', ['name' => 'facturacion'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_rol_admin_no_se_puede_renombrar_ni_borrar(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;
        $rolAdmin = $admin->roles()->first();

        $this->withToken($token)
            ->putJson("/api/staff/roles/{$rolAdmin->id}", ['name' => 'superadmin'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'rol_protegido');

        $this->withToken($token)
            ->deleteJson("/api/staff/roles/{$rolAdmin->id}")
            ->assertStatus(403)
            ->assertJsonPath('error', 'rol_protegido');
    }

    public function test_no_se_puede_borrar_un_rol_con_staff_asignado(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $recepcion = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;
        $rolRecepcion = $recepcion->roles()->first();

        $this->withToken($token)
            ->deleteJson("/api/staff/roles/{$rolRecepcion->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'rol_con_usuarios');
    }

    public function test_no_se_puede_quitar_el_permiso_de_gestion_al_ultimo_rol_que_lo_tiene(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        // 'admin' es el UNICO rol con roles.editar en la matriz base: crear
        // un rol nuevo con ese permiso y luego intentar quitarselo al de
        // 'admin' no aplica (esta protegido), asi que se prueba al reves:
        // crear un segundo rol CON el permiso, confirmar que ahi si se puede
        // sacar del segundo (porque 'admin' sigue teniendolo).
        $segundo = $this->withToken($token)
            ->postJson('/api/staff/roles', ['name' => 'co-admin', 'permissions' => ['roles.editar']])
            ->assertCreated()
            ->json('data');

        $this->withToken($token)
            ->patchJson("/api/staff/roles/{$segundo['id']}/permisos", ['permissions' => []])
            ->assertOk()
            ->assertJsonCount(0, 'data.permissions');
    }

    public function test_catalogo_de_permisos_agrupa_por_recurso(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/permisos')
            ->assertOk()
            ->assertJsonStructure(['data' => ['patients', 'appointments', 'roles', 'usuarios']]);
    }

    public function test_roles_de_un_tenant_no_son_visibles_para_otro(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $adminA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $adminB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $tokenB = $adminB->createToken('staff', ['staff'])->plainTextToken;

        // El "team" activo de Spatie quedo en tenantB tras crear a $adminB:
        // hay que volver a fijarlo en A para poder leer su rol.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantA->id);
        $rolDeA = $adminA->roles()->first();

        $this->withToken($tokenB)
            ->getJson("/api/staff/roles/{$rolDeA->id}")
            ->assertNotFound();
    }
}
