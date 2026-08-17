<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Define la matriz de roles/permisos del staff y la aprovisiona por tenant.
 *
 * Los roles viven aislados por clinica via el "team" de Spatie (mapeado a
 * tenant_id, ver config/permission.php): un rol 'admin' de una clinica no
 * autoriza nada en otra. Los permisos (los nombres) son globales, solo las
 * asignaciones rol<->permiso y usuario<->rol son por tenant.
 *
 * Idempotente (findOrCreate en todo): se puede llamar en cada registro de
 * staff nuevo sin duplicar ni pisar roles ya editados a mano por la clinica.
 */
class RoleProvisioner
{
    private const GUARD = 'staff';

    /**
     * @return array<string, list<string>>
     */
    public static function matriz(): array
    {
        $recursos = ['professionals', 'patients', 'diagnoses', 'treatments', 'budgets', 'appointments'];
        $permisos = [];

        foreach ($recursos as $recurso) {
            foreach (['ver', 'crear', 'editar', 'eliminar'] as $accion) {
                $permisos[] = "{$recurso}.{$accion}";
            }
        }

        // Marca de la clinica (nombre/logo/color): 'ver' para los 3 roles (la
        // UI lo necesita siempre visible), 'editar' solo para 'admin'.
        $permisos[] = 'tenant.ver';
        $permisos[] = 'tenant.editar';

        return [
            // Dueno/administrador de la clinica: acceso total.
            'admin' => $permisos,

            // Atiende pacientes: ve todo lo clinico, edita fichas/diagnosticos,
            // no gestiona altas de personal ni borra presupuestos/tratamientos.
            'profesional' => [
                'professionals.ver',
                'patients.ver', 'patients.editar',
                'diagnoses.ver', 'diagnoses.crear', 'diagnoses.editar',
                'treatments.ver',
                'budgets.ver',
                'appointments.ver', 'appointments.eliminar',
                'tenant.ver',
            ],

            // Recepcion/agenda: alta de pacientes y citas, sin acceso clinico
            // (diagnosticos) ni administrativo (profesionales, tratamientos).
            'recepcion' => [
                'patients.ver', 'patients.crear', 'patients.editar',
                'appointments.ver', 'appointments.crear', 'appointments.eliminar',
                'treatments.ver',
                'budgets.ver', 'budgets.crear',
                'professionals.ver',
                'tenant.ver',
            ],
        ];
    }

    /**
     * Crea (si faltan) los permisos globales y los 3 roles de este tenant con
     * su matriz de permisos ya sincronizada.
     */
    public function provisionarTenant(Tenant $tenant): void
    {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        try {
            $registrar->setPermissionsTeamId($tenant->id);
            $registrar->forgetCachedPermissions();

            // Se usa firstOrCreate de Eloquent (no el helper cacheado de
            // Spatie) para no depender del cache de permisos entre procesos
            // CLI distintos, que puede quedar desactualizado.
            foreach (self::matriz() as $rol => $permisos) {
                foreach ($permisos as $permiso) {
                    Permission::firstOrCreate(['name' => $permiso, 'guard_name' => self::GUARD]);
                }

                // El tenant_id va en el array de busqueda (no solo en los
                // valores por defecto): firstOrCreate() de Eloquent puro no
                // pasa por el create() estatico de Spatie que lo autocompleta,
                // asi que sin esto el rol quedaria compartido entre tenants.
                $role = Role::firstOrCreate([
                    'name' => $rol,
                    'guard_name' => self::GUARD,
                    'tenant_id' => $tenant->id,
                ]);
                $role->syncPermissions($permisos);
            }

            $registrar->forgetCachedPermissions();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Aprovisiona el tenant (idempotente) y asigna un rol al usuario.
     */
    public function asignarRol(User $user, string $rol): void
    {
        $this->provisionarTenant($user->tenant);

        // Se resuelve el Role explicito con guard 'staff' en vez de pasar el
        // nombre como string: el modelo User tambien es el provider del guard
        // 'web' (heredado del scaffold), y la resolucion de guard por defecto
        // de Spatie elegiria 'web' en su lugar, sin encontrar el rol.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $role = Role::where('name', $rol)
            ->where('guard_name', self::GUARD)
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();
        $user->syncRoles([$role]);
    }
}
