<?php

namespace App\Repositories;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Repositories\Contracts\StaffRepositoryInterface;

class StaffRepository implements StaffRepositoryInterface
{
    /**
     * Busca staff por (tenant, email). Ignora explicitamente el TenantScope
     * global: durante login/registro todavia no hay tenant activo en contexto,
     * asi que el filtro por tenant lo impone aqui el propio repositorio con el
     * tenant ya resuelto de la clinica, no un valor arbitrario del cliente.
     */
    public function findByTenantAndEmail(int $tenantId, string $email): ?User
    {
        return User::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return User::query()->create($data);
    }
}
