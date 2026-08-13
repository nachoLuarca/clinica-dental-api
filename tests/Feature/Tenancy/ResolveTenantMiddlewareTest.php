<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifica que el middleware ResolveTenant resuelva el tenant SIEMPRE del
 * usuario autenticado y NUNCA de un valor enviado por el cliente.
 */
class ResolveTenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'tenant'])->get('/_test/tenant', function () {
            return response()->json([
                'tenant_id' => app(TenantContext::class)->tenantId(),
            ]);
        });
    }

    public function test_resuelve_el_tenant_del_usuario_autenticado(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user, 'web')->getJson('/_test/tenant');

        $response->assertOk()->assertJson(['tenant_id' => $tenant->id]);
    }

    public function test_ignora_el_tenant_id_enviado_por_el_cliente(): void
    {
        $tenantReal = Tenant::factory()->create();
        $tenantAjeno = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantReal->id]);

        // El cliente intenta forzar otro tenant por query y por body: debe
        // ignorarse por completo y usarse el del usuario autenticado.
        $response = $this->actingAs($user, 'web')
            ->getJson('/_test/tenant?tenant_id='.$tenantAjeno->id, [
                'X-Tenant-Id' => (string) $tenantAjeno->id,
            ]);

        $response->assertOk()->assertJson(['tenant_id' => $tenantReal->id]);
    }

    public function test_sin_usuario_autenticado_no_hay_tenant(): void
    {
        $response = $this->getJson('/_test/tenant');

        $response->assertOk()->assertJson(['tenant_id' => null]);
    }
}
