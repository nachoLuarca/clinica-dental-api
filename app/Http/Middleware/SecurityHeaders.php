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
 *
 * CSP: esta es una API JSON pura, asi que por defecto no deberia poder cargar
 * ni ejecutar nada -de haber una respuesta HTML inyectada (XSS reflejado,
 * error mal serializado, etc.), el navegador no ejecutaria nada igual-. La
 * unica excepcion es /api/documentation, que sirve Swagger UI HTML real desde
 * CDN (ver DocsController) y necesita su propia politica, mas permisiva pero
 * igual acotada al host del CDN.
 */
class SecurityHeaders
{
    private const CDN_SWAGGER = 'https://cdn.jsdelivr.net';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(Request $request): string
    {
        if ($request->is('api/documentation')) {
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' ".self::CDN_SWAGGER,
                "style-src 'self' 'unsafe-inline' ".self::CDN_SWAGGER,
                "img-src 'self' data: ".self::CDN_SWAGGER,
                "connect-src 'self'",
                "frame-ancestors 'none'",
            ]);
        }

        return "default-src 'none'; frame-ancestors 'none'";
    }
}
