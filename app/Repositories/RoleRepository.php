<?php

namespace App\Repositories;

use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Role es del paquete Spatie, no un model propio del dominio: por eso este
 * repositorio filtra por tenant_id a mano en cada query (Role no usa
 * BelongsToTenant/TenantScope, esos son de app/Models).
 */
class RoleRepository implements RoleRepositoryInterface
{
    private const GUARD = 'staff';

    /**
     * @return Collection<int, Role>
     */
    public function allForTenant(int $tenantId): Collection
    {
        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', self::GUARD)
            ->with('permissions')
            ->orderBy('name')
            ->get();
    }

    public function find(int $tenantId, int $id): ?Role
    {
        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', self::GUARD)
            ->with('permissions')
            ->find($id);
    }

    public function findByName(int $tenantId, string $name): ?Role
    {
        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', self::GUARD)
            ->where('name', $name)
            ->first();
    }

    public function create(int $tenantId, string $name): Role
    {
        return Role::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'guard_name' => self::GUARD,
        ]);
    }

    public function rename(Role $role, string $name): Role
    {
        $role->update(['name' => $name]);

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function countOtrosConPermiso(int $tenantId, string $permission, int $excludeRoleId): int
    {
        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', self::GUARD)
            ->where('id', '!=', $excludeRoleId)
            ->get()
            ->filter(fn (Role $role) => $role->hasPermissionTo($permission))
            ->count();
    }

    public function tieneUsuariosAsignados(Role $role): bool
    {
        return $role->users()->exists();
    }
}
