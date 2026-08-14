<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tenant activo del request A PARTIR del usuario autenticado.
 *
 * Regla de seguridad central del multi-tenant: el tenant NUNCA se toma de
 * body, query, ni headers enviados por el cliente. Solo del usuario que ya
 * fue autenticado por alguno de los guards configurados.
 *
 * Punto de extension (paso 3): agregar 'staff' y 'paciente' a
 * config('tenancy.guards'). Este middleware ya los recorrera sin cambios.
 */
class ResolveTenant
{
    public function __construct(
        protected TenantContext $context,
        protected PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveAuthenticatedUser($request);

        if ($user !== null && isset($user->tenant_id)) {
            $this->context->setTenantId((int) $user->tenant_id);

            // Los roles/permisos (Spatie "teams") estan aislados por tenant con
            // la misma columna que el resto del sistema: sin esto, un rol
            // "admin" de una clinica autorizaria en cualquier otra.
            $this->permissionRegistrar->setPermissionsTeamId((int) $user->tenant_id);
        }

        return $next($request);
    }

    /**
     * Devuelve el primer usuario autenticado entre los guards configurados.
     * Ignora guards que no existan en config('auth.guards') para poder listar
     * guards futuros sin romper.
     */
    protected function resolveAuthenticatedUser(Request $request): mixed
    {
        $definedGuards = array_keys(config('auth.guards', []));

        foreach (config('tenancy.guards', []) as $guard) {
            if (! in_array($guard, $definedGuards, true)) {
                continue;
            }

            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return null;
    }
}
