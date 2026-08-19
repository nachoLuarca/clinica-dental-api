<?php

namespace App\Services\Auth;

use App\Exceptions\OperacionSobreSiMismoException;
use App\Exceptions\UltimoAdminException;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\StaffRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gestion del staff de la propia clinica por un admin: alta, edicion,
 * asignacion de rol, activar/desactivar y reseteo de password.
 *
 * Sin auditoria (a diferencia de col_api): fuera de alcance para esta etapa
 * de prueba/desarrollo, se puede sumar despues si hace falta trazabilidad.
 */
class UserManagementService
{
    public function __construct(
        private readonly StaffRepositoryInterface $staff,
        private readonly RoleRepositoryInterface $roles,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{rol?: string, activo?: bool, nombre?: string}  $filters
     */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->staff->paginate($filters, $perPage);
    }

    public function find(int $id): User
    {
        return $this->staff->find($id)
            ?? throw (new ModelNotFoundException)->setModel(User::class);
    }

    /**
     * Crea el staff y le asigna exactamente un rol, en una sola transaccion.
     *
     * @param  array{name: string, email: string, password: string, rol: string}  $data
     */
    public function create(array $data): User
    {
        $this->assertEmailUnico($data['email']);

        return DB::transaction(function () use ($data) {
            $user = $this->staff->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'activo' => true,
            ]);

            $this->asignarRolSinCustodia($user, $data['rol']);

            return $this->staff->find($user->id);
        });
    }

    /**
     * Solo datos basicos: password y rol van por sus metodos dedicados.
     *
     * @param  array{name?: string, email?: string}  $data
     */
    public function update(int $id, array $data): User
    {
        $user = $this->find($id);

        if (isset($data['email']) && $data['email'] !== $user->email) {
            $this->assertEmailUnico($data['email']);
        }

        return $this->staff->update($user, array_intersect_key($data, array_flip(['name', 'email'])));
    }

    /**
     * Reemplaza el rol del staff. Protege que un admin no se quite a si mismo
     * el ultimo rol capaz de gestionar roles/usuarios, y que la clinica nunca
     * se quede sin ninguno.
     */
    public function asignarRol(int $id, string $rolNombre, User $actor): User
    {
        $user = $this->find($id);
        $tenantId = $this->requireTenantId();

        $rolNuevo = $this->roles->findByName($tenantId, $rolNombre);
        if ($rolNuevo === null) {
            throw (new ModelNotFoundException)->setModel(Role::class);
        }

        $teniaGestion = $user->can(RoleProvisioner::PERMISO_GESTION);
        $tendraGestion = $rolNuevo->hasPermissionTo(RoleProvisioner::PERMISO_GESTION);
        $quitaGestion = $teniaGestion && ! $tendraGestion;

        if ($quitaGestion && $user->id === $actor->id) {
            throw new OperacionSobreSiMismoException('No puedes quitarte a ti mismo el permiso de gestionar roles/usuarios.');
        }

        return DB::transaction(function () use ($user, $rolNuevo, $quitaGestion) {
            if ($quitaGestion
                && $this->staff->countActivosConPermiso(RoleProvisioner::PERMISO_GESTION, $user->id) === 0) {
                throw new UltimoAdminException;
            }

            $this->asignarRolSinCustodia($user, $rolNuevo->name);

            return $this->staff->find($user->id);
        });
    }

    /**
     * Activa/desactiva al staff. Al desactivar, revoca todos sus tokens (debe
     * volver a loguearse si se reactiva).
     */
    public function setEstado(int $id, bool $activo, User $actor): User
    {
        $user = $this->find($id);

        if (! $activo) {
            if ($user->id === $actor->id) {
                throw new OperacionSobreSiMismoException('No puedes desactivar tu propia cuenta.');
            }

            if ($user->can(RoleProvisioner::PERMISO_GESTION)
                && $this->staff->countActivosConPermiso(RoleProvisioner::PERMISO_GESTION, $user->id) === 0) {
                throw new UltimoAdminException(
                    'No se puede desactivar al ultimo staff activo que puede gestionar roles/usuarios.'
                );
            }
        }

        return DB::transaction(function () use ($user, $activo) {
            $user = $this->staff->update($user, ['activo' => $activo]);

            if (! $activo) {
                $user->tokens()->delete();
            }

            return $this->staff->find($user->id);
        });
    }

    public function resetearPassword(int $id, string $nuevaPassword): void
    {
        $user = $this->find($id);
        $this->staff->update($user, ['password' => Hash::make($nuevaPassword)]);
        $user->tokens()->delete();
    }

    private function asignarRolSinCustodia(User $user, string $rolNombre): void
    {
        $tenantId = $this->requireTenantId();
        $rol = $this->roles->findByName($tenantId, $rolNombre)
            ?? throw (new ModelNotFoundException)->setModel(Role::class);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $user->syncRoles([$rol]);
    }

    private function assertEmailUnico(string $email): void
    {
        $tenantId = $this->requireTenantId();

        if ($this->staff->findByTenantAndEmail($tenantId, $email) !== null) {
            throw ValidationException::withMessages([
                'email' => ['Ya existe una cuenta de staff con este correo en la clinica.'],
            ]);
        }
    }

    private function requireTenantId(): int
    {
        return $this->tenant->tenantId()
            ?? throw new \RuntimeException('No hay tenant activo en el contexto.');
    }
}
