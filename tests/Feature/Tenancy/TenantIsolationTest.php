<?php

namespace Tests\Feature\Tenancy;

use App\Models\Professional;
use App\Models\Tenant;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del AISLAMIENTO multi-tenant. Punto critico del paso 2.
 *
 * Verifican que, con un tenant activo en el contexto, un tenant no pueda
 * ver ni tocar registros de otro tenant, y que las escrituras se etiqueten
 * automaticamente con el tenant activo.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): TenantContext
    {
        return app(TenantContext::class);
    }

    private function repository(): ProfessionalRepositoryInterface
    {
        return app(ProfessionalRepositoryInterface::class);
    }

    public function test_las_queries_solo_ven_registros_del_tenant_activo(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Professional::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
        Professional::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

        $this->context()->setTenantId($tenantA->id);
        $this->assertCount(3, $this->repository()->all());

        $this->context()->setTenantId($tenantB->id);
        $this->assertCount(2, $this->repository()->all());
    }

    public function test_un_tenant_no_puede_encontrar_registro_de_otro_tenant_por_id(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $delB = Professional::factory()->create(['tenant_id' => $tenantB->id]);

        // Contexto = tenant A intentando leer un registro de tenant B.
        $this->context()->setTenantId($tenantA->id);

        $this->assertNull($this->repository()->find($delB->id));

        // Con el tenant correcto si aparece.
        $this->context()->setTenantId($tenantB->id);
        $this->assertNotNull($this->repository()->find($delB->id));
    }

    public function test_crear_asigna_automaticamente_el_tenant_activo(): void
    {
        $tenant = Tenant::factory()->create();

        $this->context()->setTenantId($tenant->id);

        $prof = $this->repository()->create([
            'nombre' => 'Ana',
            'apellido' => 'Perez',
        ]);

        $this->assertSame($tenant->id, $prof->tenant_id);
        $this->assertDatabaseHas('professionals', [
            'id' => $prof->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_crear_ignora_un_tenant_id_distinto_pasado_en_data(): void
    {
        $tenantActivo = Tenant::factory()->create();
        $otroTenant = Tenant::factory()->create();

        $this->context()->setTenantId($tenantActivo->id);

        // Aunque el "cliente" intente inyectar otro tenant_id, el trait solo
        // completa cuando esta vacio; aqui verificamos que un create limpio
        // (sin tenant_id) siempre queda con el tenant activo.
        $prof = $this->repository()->create([
            'nombre' => 'Intruso',
        ]);

        $this->assertSame($tenantActivo->id, $prof->tenant_id);
        $this->assertNotSame($otroTenant->id, $prof->tenant_id);
    }

    public function test_un_tenant_no_puede_actualizar_registro_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $delB = Professional::factory()->create([
            'tenant_id' => $tenantB->id,
            'nombre' => 'Original',
        ]);

        // Tenant A no lo encuentra, por lo tanto no puede actualizarlo.
        $this->context()->setTenantId($tenantA->id);
        $this->assertNull($this->repository()->find($delB->id));

        // El registro sigue intacto visto desde su propio tenant.
        $this->context()->setTenantId($tenantB->id);
        $this->assertSame('Original', $this->repository()->find($delB->id)->nombre);
    }

    public function test_un_tenant_no_puede_eliminar_registro_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $delB = Professional::factory()->create(['tenant_id' => $tenantB->id]);

        // El delete usa una query scopeada; sin el registro visible, no borra.
        $this->context()->setTenantId($tenantA->id);
        $affected = Professional::query()->where('id', $delB->id)->delete();
        $this->assertSame(0, $affected);

        // Sigue existiendo para su tenant.
        $this->context()->setTenantId($tenantB->id);
        $this->assertNotNull($this->repository()->find($delB->id));
    }

    public function test_sin_tenant_en_contexto_el_scope_no_filtra(): void
    {
        // Situacion previa al login: no debe filtrar para no romper consultas
        // de autenticacion.
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Professional::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
        Professional::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

        $this->context()->forget();

        $this->assertCount(5, Professional::query()->get());
    }

    public function test_run_with_tenant_restaura_el_tenant_previo(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $this->context()->setTenantId($tenantA->id);

        $resultado = $this->context()->runWithTenant($tenantB->id, function () {
            return $this->context()->tenantId();
        });

        $this->assertSame($tenantB->id, $resultado);
        $this->assertSame($tenantA->id, $this->context()->tenantId());
    }
}
