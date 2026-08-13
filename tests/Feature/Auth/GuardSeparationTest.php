<?php

namespace Tests\Feature\Auth;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Punto critico del paso 3: los dos guards son INDEPENDIENTES. Un token de un
 * guard nunca autoriza endpoints del otro. La separacion la garantiza Sanctum
 * validando que el modelo del token coincida con el provider del guard.
 */
class GuardSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function staffToken(Tenant $tenant): string
    {
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        return $staff->createToken('staff', ['staff'])->plainTextToken;
    }

    private function patientToken(Tenant $tenant): string
    {
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);

        return $patient->createToken('paciente', ['paciente'])->plainTextToken;
    }

    public function test_token_de_staff_accede_a_endpoint_de_staff(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffToken($tenant))
            ->getJson('/api/staff/me')
            ->assertOk();
    }

    public function test_token_de_staff_n_o_accede_a_endpoint_de_paciente(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->staffToken($tenant))
            ->getJson('/api/paciente/me')
            ->assertUnauthorized();
    }

    public function test_token_de_paciente_accede_a_endpoint_de_paciente(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->patientToken($tenant))
            ->getJson('/api/paciente/me')
            ->assertOk();
    }

    public function test_token_de_paciente_n_o_accede_a_endpoint_de_staff(): void
    {
        $tenant = Tenant::factory()->create();

        $this->withToken($this->patientToken($tenant))
            ->getJson('/api/staff/me')
            ->assertUnauthorized();
    }

    public function test_sin_token_los_endpoints_privados_responden_401(): void
    {
        $this->getJson('/api/staff/me')->assertUnauthorized();
        $this->getJson('/api/paciente/me')->assertUnauthorized();
    }
}
