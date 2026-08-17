<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Registro / login del guard 'staff'.
 */
class StaffAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_puede_registrarse_y_recibe_token(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/api/staff/register', [
            'clinica' => $tenant->slug,
            'name' => 'Dra. Ana',
            'email' => 'ana@clinica.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'data' => ['id', 'email', 'roles']])
            // Auto-registro publico = sin rol (paso 10, roles editables): el
            // rol de base 'recepcion' se puede renombrar/borrar, asi que ya
            // no hay ninguno "seguro" para asignar de antemano. Un admin de
            // la clinica asigna el rol despues via PATCH /users/{id}/rol.
            ->assertJsonPath('data.roles', []);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'ana@clinica.test',
        ]);
    }

    public function test_staff_puede_hacer_login_con_credenciales_correctas(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'ana@clinica.test',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/staff/login', [
            'clinica' => $tenant->slug,
            'email' => 'ana@clinica.test',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_y_me_incluyen_los_roles_del_usuario(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->rol('profesional')->create([
            'tenant_id' => $tenant->id,
            'email' => 'ana@clinica.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/staff/login', [
            'clinica' => $tenant->slug,
            'email' => 'ana@clinica.test',
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('data.roles', ['profesional']);

        $token = $staff->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['profesional']);
    }

    public function test_login_con_password_incorrecta_falla(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'ana@clinica.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/staff/login', [
            'clinica' => $tenant->slug,
            'email' => 'ana@clinica.test',
            'password' => 'incorrecta',
        ])->assertStatus(422);
    }

    public function test_login_con_clinica_inexistente_falla(): void
    {
        $this->postJson('/api/staff/login', [
            'clinica' => 'no-existe',
            'email' => 'ana@clinica.test',
            'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_mismo_email_puede_existir_como_staff_en_dos_clinicas(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->postJson('/api/staff/register', [
            'clinica' => $tenantA->slug,
            'name' => 'Ana A',
            'email' => 'ana@correo.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();

        // El mismo correo en OTRA clinica debe permitirse (unico por tenant).
        $this->postJson('/api/staff/register', [
            'clinica' => $tenantB->slug,
            'name' => 'Ana B',
            'email' => 'ana@correo.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated();
    }

    public function test_email_duplicado_en_la_misma_clinica_falla(): void
    {
        $tenant = Tenant::factory()->create();

        $payload = [
            'clinica' => $tenant->slug,
            'name' => 'Ana',
            'email' => 'ana@correo.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ];

        $this->postJson('/api/staff/register', $payload)->assertCreated();
        $this->postJson('/api/staff/register', $payload)->assertStatus(422);
    }

    public function test_registro_valida_campos_requeridos(): void
    {
        $this->postJson('/api/staff/register', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['clinica', 'name', 'email', 'password']);
    }
}
