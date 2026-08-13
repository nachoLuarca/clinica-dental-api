<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agrega headers de seguridad basicos a todas las respuestas de la API.
 *
 * Son cabeceras defensivas de bajo costo: evitan sniffing de MIME, embebido en
 * iframes de terceros y filtracion del Referer hacia otros dominios. HSTS se
 * deja para la capa de despliegue (requiere HTTPS real), pero se aplica cuando
 * la request ya llega por HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
