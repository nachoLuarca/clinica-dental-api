<?php

namespace Tests\Feature\Auth;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Registro / login del guard 'paciente' (correo, contrasena, nombre, fecha de
 * nacimiento).
 */
class PatientAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_paciente_puede_registrarse_y_recibe_token(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->postJson('/api/paciente/register', [
            'clinica' => $tenant->slug,
            'nombre' => 'Juan Perez',
            'email' => 'juan@correo.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'fecha_nacimiento' => '1990-05-20',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'token_type', 'data' => ['id', 'email', 'fecha_nacimiento']]);

        $this->assertDatabaseHas('patients', [
            'tenant_id' => $tenant->id,
            'email' => 'juan@correo.test',
        ]);
    }

    public function test_paciente_puede_hacer_login(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'juan@correo.test',
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/paciente/login', [
            'clinica' => $tenant->slug,
            'email' => 'juan@correo.test',
            'password' => 'secret123',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_registro_valida_fecha_de_nacimiento(): void
    {
        $tenant = Tenant::factory()->create();

        $this->postJson('/api/paciente/register', [
            'clinica' => $tenant->slug,
            'nombre' => 'Juan',
            'email' => 'juan@correo.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'fecha_nacimiento' => 'no-es-fecha',
        ])->assertStatus(422)->assertJsonValidationErrors(['fecha_nacimiento']);
    }

    public function test_mismo_email_puede_registrarse_como_paciente_en_dos_clinicas(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $base = [
            'nombre' => 'Juan',
            'email' => 'juan@correo.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'fecha_nacimiento' => '1990-05-20',
        ];

        $this->postJson('/api/paciente/register', ['clinica' => $tenantA->slug] + $base)->assertCreated();
        $this->postJson('/api/paciente/register', ['clinica' => $tenantB->slug] + $base)->assertCreated();
    }
}
