<?php

namespace App\Services\Auth;

use App\Exceptions\RolConUsuariosException;
use App\Exceptions\RolProtegidoException;
use App\Exceptions\UltimoAdminException;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles editables por la propia clinica (paso 10): crear, renombrar, borrar
 * y reemplazar la matriz de permisos de un rol. El rol 'admin' esta protegido
 * (RoleProvisioner::ROL_PROTEGIDO): siempre existe y siempre puede gestionar
 * roles/usuarios, para que la clinica nunca se quede sin nadie que administre.
 */
class RoleManagementService
{
    public function __construct(
        private readonly RoleRepositoryInterface $roles,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return Collection<int, Role>
     */
    public function all(): Collection
    {
        return $this->roles->allForTenant($this->requireTenantId());
    }

    public function find(int $id): Role
    {
        return $this->roles->find($this->requireTenantId(), $id)
            ?? throw (new ModelNotFoundException)->setModel(Role::class);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    public function create(string $name, array $permisos = []): Role
    {
        $tenantId = $this->requireTenantId();
        $this->assertNombreUnico($tenantId, $name);

        return DB::transaction(function () use ($tenantId, $name, $permisos) {
            $role = $this->roles->create($tenantId, $name);
            $this->setPermisosTeam($tenantId);
            $role->syncPermissions($permisos);

            return $role->load('permissions');
        });
    }

    /**
     * @param  array{name?: string, permissions?: array<int, string>}  $data
     */
    public function update(int $id, array $data): Role
    {
        $role = $this->find($id);
        $this->assertNoProtegido($role);

        if (isset($data['name']) && $data['name'] !== $role->name) {
            $this->assertNombreUnico($this->requireTenantId(), $data['name']);
            $role = $this->roles->rename($role, $data['name']);
        }

        if (isset($data['permissions'])) {
            $role = $this->reemplazarPermisos($role, $data['permissions']);
        }

        return $role->load('permissions');
    }

    /**
     * @param  array<int, string>  $permisos
     */
    public function updatePermisos(int $id, array $permisos): Role
    {
        $role = $this->find($id);
        $this->assertNoProtegido($role);

        return $this->reemplazarPermisos($role, $permisos)->load('permissions');
    }

    public function delete(int $id): void
    {
        $role = $this->find($id);
        $this->assertNoProtegido($role);

        if ($this->roles->tieneUsuariosAsignados($role)) {
            throw new RolConUsuariosException;
        }

        $this->roles->delete($role);
    }

    /**
     * @param  array<int, string>  $permisos
     */
    private function reemplazarPermisos(Role $role, array $permisos): Role
    {
        $tenantId = $this->requireTenantId();
        $quitaGestion = $role->hasPermissionTo(RoleProvisioner::PERMISO_GESTION)
            && ! in_array(RoleProvisioner::PERMISO_GESTION, $permisos, true);

        return DB::transaction(function () use ($role, $permisos, $tenantId, $quitaGestion) {
            if ($quitaGestion
                && $this->roles->countOtrosConPermiso($tenantId, RoleProvisioner::PERMISO_GESTION, $role->id) === 0) {
                throw new UltimoAdminException(
                    'No se puede quitar el permiso de gestionar roles/usuarios al ultimo rol que lo tiene.'
                );
            }

            $this->setPermisosTeam($tenantId);
            $role->syncPermissions($permisos);

            return $role;
        });
    }

    private function assertNoProtegido(Role $role): void
    {
        if ($role->name === RoleProvisioner::ROL_PROTEGIDO) {
            throw new RolProtegidoException;
        }
    }

    private function assertNombreUnico(int $tenantId, string $name): void
    {
        if ($this->roles->findByName($tenantId, $name) !== null) {
            throw ValidationException::withMessages([
                'name' => ['Ya existe un rol con este nombre en la clinica.'],
            ]);
        }
    }

    private function setPermisosTeam(int $tenantId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
    }

    private function requireTenantId(): int
    {
        return $this->tenant->tenantId()
            ?? throw new \RuntimeException('No hay tenant activo en el contexto.');
    }
}
