<?php

namespace Tests\Feature\Cruds;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

class PatientRegistryCrudTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    public function test_staff_puede_registrar_paciente_sin_password(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [
                'nombre' => 'Juan Paciente',
                'email' => 'juan@correo.test',
                'telefono' => '+56911111111',
                'fecha_nacimiento' => '1990-05-20',
                'notas' => 'Alergico a la penicilina',
            ]);

        $response->assertCreated()->assertJsonPath('data.nombre', 'Juan Paciente');

        $this->assertDatabaseHas('patients', [
            'email' => 'juan@correo.test',
            'tenant_id' => $tenant->id,
            'password' => null,
        ]);
    }

    public function test_staff_puede_registrar_paciente_con_password_hasheada(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [
                'nombre' => 'Con Pass',
                'email' => 'pass@correo.test',
                'fecha_nacimiento' => '1990-05-20',
                'password' => 'secret123',
            ])
            ->assertCreated();

        $patient = Patient::withoutGlobalScopes()->where('email', 'pass@correo.test')->first();
        $this->assertNotNull($patient->password);
        $this->assertNotSame('secret123', $patient->password);
    }

    public function test_email_duplicado_en_misma_clinica_falla(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'email' => 'dup@correo.test']);

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [
                'nombre' => 'Otro',
                'email' => 'dup@correo.test',
                'fecha_nacimiento' => '1990-05-20',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_staff_puede_listar_actualizar_y_eliminar_paciente(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->staffTokenFor($tenant);
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id, 'nombre' => 'Old']);

        $this->withToken($token)->getJson('/api/staff/patients')
            ->assertOk()->assertJsonStructure(['data', 'meta', 'links']);

        $this->withToken($token)->putJson("/api/staff/patients/{$patient->id}", ['nombre' => 'Nuevo'])
            ->assertOk()->assertJsonPath('data.nombre', 'Nuevo');

        $this->withToken($token)->deleteJson("/api/staff/patients/{$patient->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('patients', ['id' => $patient->id]);
    }

    public function test_crear_paciente_valida_campos_requeridos(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffTokenFor($tenant))
            ->postJson('/api/staff/patients', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'email', 'fecha_nacimiento']);
    }
}
