<?php

namespace Tests\Feature\Auth;

use App\Models\Patient;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifica el aislamiento multi-tenant con AUTH REAL por token: el tenant se
 * resuelve del usuario autenticado (via ResolveTenant), y las queries scopeadas
 * solo ven datos del tenant de ese usuario, para ambos guards.
 */
class AuthTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rutas de prueba protegidas por cada guard + tenant, que devuelven
        // datos tenant-scoped y el tenant activo resuelto.
        Route::middleware(['auth:staff', 'tenant'])->get('/api/_test/staff-scope', function () {
            return response()->json([
                'tenant_id' => app(TenantContext::class)->tenantId(),
                'profesionales' => Professional::query()->count(),
            ]);
        });

        Route::middleware(['auth:paciente', 'tenant'])->get('/api/_test/paciente-scope', function () {
            return response()->json([
                'tenant_id' => app(TenantContext::class)->tenantId(),
                'profesionales' => Professional::query()->count(),
            ]);
        });
    }

    public function test_staff_solo_ve_datos_de_su_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Professional::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        Professional::factory()->count(5)->create(['tenant_id' => $tenantB->id]);

        $staffA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $token = $staffA->createToken('staff', ['staff'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/_test/staff-scope')
            ->assertOk()
            ->assertJson([
                'tenant_id' => $tenantA->id,
                'profesionales' => 3,
            ]);
    }

    public function test_paciente_resuelve_su_propio_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Professional::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Professional::factory()->count(4)->create(['tenant_id' => $tenantB->id]);

        $patientB = Patient::factory()->create(['tenant_id' => $tenantB->id]);
        $token = $patientB->createToken('paciente', ['paciente'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/_test/paciente-scope')
            ->assertOk()
            ->assertJson([
                'tenant_id' => $tenantB->id,
                'profesionales' => 4,
            ]);
    }
}
