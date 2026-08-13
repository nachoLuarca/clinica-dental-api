<?php

namespace Tests\Feature\Seguridad;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Endurecimiento (paso 7): verificacion de abilities del token Sanctum como
 * defensa en profundidad sobre la separacion de guards. Aunque el guard ya
 * garantiza que un token de paciente no autentique como staff, el chequeo de
 * ability corta ademas los tokens del modelo correcto emitidos sin el scope.
 */
class TokenAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_token_de_staff_con_ability_correcta_accede(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        $token = $staff->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/me')
            ->assertOk();
    }

    public function test_token_de_staff_sin_ability_staff_es_rechazado(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        // Token del modelo correcto pero SIN el ability 'staff'.
        $token = $staff->createToken('staff', ['otro-scope'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/staff/me')
            ->assertForbidden();
    }

    public function test_token_de_paciente_con_ability_correcta_accede(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $token = $patient->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/paciente/me')
            ->assertOk();
    }

    public function test_token_de_paciente_sin_ability_paciente_es_rechazado(): void
    {
        $tenant = Tenant::factory()->create();
        $patient = Patient::factory()->create(['tenant_id' => $tenant->id]);
        $token = $patient->createToken('paciente', ['otro-scope'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/paciente/me')
            ->assertForbidden();
    }
}
