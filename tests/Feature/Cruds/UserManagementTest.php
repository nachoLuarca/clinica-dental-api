<?php

namespace Tests\Feature\Cruds;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Gestion del staff de la propia clinica (paso 10): alta con rol, cambio de
 * rol, activar/desactivar, con las salvaguardas de "ultimo admin" y
 * "operacion sobre si mismo".
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_admin_puede_crear_staff_con_un_rol(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/staff/users', [
                'name' => 'Nueva Recepcion',
                'email' => 'nueva@demo.cl',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'rol' => 'recepcion',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'nueva@demo.cl')
            ->assertJsonPath('data.roles.0.name', 'recepcion');
    }

    public function test_recepcion_no_puede_crear_staff(): void
    {
        $tenant = Tenant::factory()->create();
        $recepcion = User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $token = $recepcion->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/staff/users', [
                'name' => 'X', 'email' => 'x@demo.cl',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'rol' => 'recepcion',
            ])
            ->assertForbidden();
    }

    public function test_admin_no_puede_desactivarse_a_si_mismo(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/staff/users/{$admin->id}/estado", ['activo' => false])
            ->assertStatus(422)
            ->assertJsonPath('error', 'operacion_sobre_si_mismo');
    }

    public function test_admin_no_puede_quitarse_a_si_mismo_el_rol_admin(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/staff/users/{$admin->id}/rol", ['rol' => 'recepcion'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'operacion_sobre_si_mismo');
    }

    public function test_se_puede_desactivar_un_admin_si_queda_otro_activo(): void
    {
        // Un solo token por test (el guard de Sanctum memoiza el usuario
        // resuelto dentro del mismo metodo, ver PatientRegistryCrudTest).
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $segundoAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $segundoAdmin->createToken('staff', ['staff'])->plainTextToken;

        // El segundo admin desactiva al primero: se permite porque el propio
        // segundo admin, activo, sigue cubriendo la salvaguarda.
        $this->withToken($token)
            ->patchJson("/api/staff/users/{$admin->id}/estado", ['activo' => false])
            ->assertOk();
    }

    public function test_no_se_puede_desactivar_al_ultimo_admin_activo(): void
    {
        $tenant = Tenant::factory()->create();
        $unicoAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        // Segundo admin, ya inactivo: no cuenta como "otro activo".
        User::factory()->create(['tenant_id' => $tenant->id, 'activo' => false]);
        // Actor con permiso para desactivar staff pero SIN el rol admin, para
        // no disparar la guarda de auto-operacion (distinta de esta).
        $gestorNoAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $gestorNoAdmin->syncRoles([]);
        // Se resuelve el Permission explicito con guard 'staff' en vez del
        // nombre como string: el modelo User tambien es provider del guard
        // 'web', y la resolucion de guard por defecto de Spatie elegiria
        // 'web' en su lugar, sin encontrar el permiso.
        $gestorNoAdmin->givePermissionTo(
            Permission::where('name', 'usuarios.editar')->where('guard_name', 'staff')->firstOrFail()
        );
        $token = $gestorNoAdmin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->patchJson("/api/staff/users/{$unicoAdmin->id}/estado", ['activo' => false])
            ->assertStatus(422)
            ->assertJsonPath('error', 'ultimo_admin');
    }

    public function test_login_de_staff_desactivado_falla(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'inactivo@demo.cl',
            'activo' => false,
        ]);

        $this->postJson('/api/staff/login', [
            'clinica' => $tenant->slug,
            'email' => 'inactivo@demo.cl',
            'password' => 'password',
        ])->assertStatus(422);
    }

    public function test_listado_de_staff_filtra_por_rol(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->rol('recepcion')->create(['tenant_id' => $tenant->id]);
        $token = $admin->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/users?rol=recepcion')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
