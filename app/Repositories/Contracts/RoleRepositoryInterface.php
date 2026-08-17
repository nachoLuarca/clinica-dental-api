<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * @return Collection<int, Role>
     */
    public function allForTenant(int $tenantId): Collection;

    public function find(int $tenantId, int $id): ?Role;

    public function findByName(int $tenantId, string $name): ?Role;

    public function create(int $tenantId, string $name): Role;

    public function rename(Role $role, string $name): Role;

    public function delete(Role $role): void;

    /**
     * Cuenta otros roles del tenant (excluyendo $excludeRoleId) que tienen el
     * permiso dado, sin importar si algun usuario los tiene asignados.
     */
    public function countOtrosConPermiso(int $tenantId, string $permission, int $excludeRoleId): int;

    public function tieneUsuariosAsignados(Role $role): bool;
}
