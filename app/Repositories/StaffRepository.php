<?php

namespace App\Repositories;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Repositories\Contracts\StaffRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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

    /**
     * @param  array{rol?: string, activo?: bool, nombre?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->when(isset($filters['rol']), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $filters['rol'])))
            ->when(isset($filters['activo']), fn ($q) => $q->where('activo', $filters['activo']))
            ->when(isset($filters['nombre']) && $filters['nombre'] !== '', fn ($q) => $q->where(
                fn ($q2) => $q2->where('name', 'like', "%{$filters['nombre']}%")
                    ->orWhere('email', 'like', "%{$filters['nombre']}%")
            ))
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id): ?User
    {
        return User::query()->with('roles.permissions')->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    public function countActivosConPermiso(string $permission, int $excludeUserId): int
    {
        return User::query()
            ->where('activo', true)
            ->where('id', '!=', $excludeUserId)
            ->get()
            ->filter(fn (User $u) => $u->can($permission))
            ->count();
    }
}
