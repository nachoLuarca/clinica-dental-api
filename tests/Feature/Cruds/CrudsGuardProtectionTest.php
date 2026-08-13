<?php

namespace Tests\Feature\Cruds;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithStaffAuth;
use Tests\TestCase;

/**
 * Los CRUDs de gestion son exclusivos del guard 'staff'. Un token de paciente
 * NO debe poder tocarlos, y sin token responden 401.
 */
class CrudsGuardProtectionTest extends TestCase
{
    use InteractsWithStaffAuth, RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function endpoints(): array
    {
        return [
            ['GET', '/api/staff/professionals'],
            ['POST', '/api/staff/professionals'],
            ['GET', '/api/staff/patients'],
            ['POST', '/api/staff/patients'],
            ['GET', '/api/staff/treatments'],
            ['POST', '/api/staff/treatments'],
            ['GET', '/api/staff/budgets'],
            ['POST', '/api/staff/budgets'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_token_de_paciente_no_accede_a_endpoints_de_staff(string $method, string $uri): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->patientTokenFor($tenant);

        $this->withToken($token)->json($method, $uri)->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_sin_token_los_endpoints_de_staff_responden_401(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }
}
