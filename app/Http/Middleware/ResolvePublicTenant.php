<?php

namespace App\Http\Middleware;

use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el tenant para endpoints PUBLICOS (sin usuario autenticado): el
 * catalogo de tratamientos y la disponibilidad que consume el sitio publico
 * de pacientes.
 *
 * A diferencia de ResolveTenant (que saca el tenant del usuario autenticado),
 * aca no hay sesion: el frontend publico indica QUE clinica esta mostrando
 * mediante el slug enviado en el header 'X-Clinica'. Esto NO viola la regla de
 * "nunca confiar en un tenant_id del cliente": el slug solo SELECCIONA que
 * catalogo publico mostrar (dato de lectura, no un limite de seguridad de
 * escritura), y se valida contra la tabla de tenants. El id interno del tenant
 * jamas viaja por el cliente.
 */
class ResolvePublicTenant
{
    public function __construct(
        protected TenantContext $context,
        protected TenantRepositoryInterface $tenants,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) config('tenancy.public_header', 'X-Clinica');
        $slug = trim((string) $request->header($header, ''));

        if ($slug === '') {
            return response()->json([
                'message' => "Falta el identificador de clinica en el header {$header}.",
            ], 400);
        }

        $tenant = $this->tenants->findBySlug($slug);

        if ($tenant === null || ! $tenant->activo) {
            return response()->json([
                'message' => 'La clinica indicada no existe o no esta disponible.',
            ], 404);
        }

        $this->context->setTenantId((int) $tenant->id);

        return $next($request);
    }
}
