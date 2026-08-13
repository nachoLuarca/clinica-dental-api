<?php

namespace App\Models\Scopes;

use App\Models\Concerns\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope que filtra automaticamente cada query por el tenant activo.
 *
 * Si no hay tenant en el contexto (por ejemplo durante el login, antes de
 * resolver al usuario) el scope no filtra nada, para no romper las consultas
 * que necesariamente ocurren antes de conocer el tenant.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if (! $context->hasTenant()) {
            return;
        }

        /** @var BelongsToTenant $model */
        $column = $model->getQualifiedTenantColumn();

        $builder->where($column, $context->tenantId());
    }
}
