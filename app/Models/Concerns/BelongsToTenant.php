<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait para modelos aislados por tenant.
 *
 * - Aplica el TenantScope global: toda query queda filtrada por el tenant
 *   activo automaticamente.
 * - Al crear un registro le asigna el tenant_id activo si no trae uno.
 *
 * El tenant SIEMPRE proviene del TenantContext (poblado desde el usuario
 * autenticado por el middleware), nunca de input del cliente.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            /** @var self $model */
            $context = app(TenantContext::class);
            $column = $model->getTenantColumn();

            if ($context->hasTenant() && empty($model->{$column})) {
                $model->{$column} = $context->tenantId();
            }
        });
    }

    public function getTenantColumn(): string
    {
        return config('tenancy.tenant_column', 'tenant_id');
    }

    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable().'.'.$this->getTenantColumn();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, $this->getTenantColumn());
    }
}
