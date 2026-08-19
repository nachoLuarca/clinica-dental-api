<?php

use App\Http\Middleware\ResolvePublicTenant;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Headers de seguridad basicos en todas las respuestas de la API.
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        // Alias de middleware del proyecto:
        //  - 'tenant': resuelve el tenant del usuario AUTENTICADO (nunca de input
        //    del cliente). Debe correr SIEMPRE despues de la autenticacion.
        //  - 'tenant.publico': resuelve el tenant por slug de clinica (header
        //    X-Clinica) para endpoints publicos sin sesion (catalogo/disponibilidad).
        //  - 'abilities'/'ability': verificacion de abilities del token Sanctum
        //    (defensa en profundidad sobre la separacion de guards staff/paciente).
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.publico' => ResolvePublicTenant::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Esta es una API pura (sin rutas 'web' de login): por defecto, un
        // request sin token que NO manda 'Accept: application/json' hace que
        // el middleware Authenticate intente armar un redirect a la ruta
        // 'login' -que no existe- y explota con 500 RouteNotFoundException
        // en vez de devolver un 401 limpio. Se anula el redirect (null
        // siempre) para que nunca dependa de que el cliente mande ese header.
        Authenticate::redirectUsing(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Spatie lanza esta excepcion en ingles y sin el shape {message, error}
        // del resto de la API; ademas, sin interceptarla, con APP_DEBUG=true
        // (modo prueba de este proyecto) se filtra un stack trace completo de
        // ~150 lineas en la respuesta 403.
        $exceptions->render(fn (UnauthorizedException $e, Request $request) => response()->json([
            'message' => 'No tienes permiso para realizar esta accion.',
            'error' => 'permiso_denegado',
        ], 403));

        // Sin interceptarla, un 404 "de verdad" (recurso inexistente o de
        // otro tenant) tambien filtraba un stack trace completo con
        // APP_DEBUG=true, igual que el UnauthorizedException de arriba. El
        // Handler de Laravel convierte ModelNotFoundException en
        // NotFoundHttpException ANTES de llegar a los render() custom, asi
        // que hay que interceptar esta ultima (no la primera).
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => response()->json([
            'message' => 'El recurso solicitado no existe.',
            'error' => 'no_encontrado',
        ], 404));

        // Mismo shape {message, error} en espanol que el resto de los errores
        // esperados de la API (sin esto, el default de Laravel es en ingles:
        // "Unauthenticated.").
        $exceptions->render(fn (AuthenticationException $e, Request $request) => response()->json([
            'message' => 'No estas autenticado. Inicia sesion para continuar.',
            'error' => 'no_autenticado',
        ], 401));

        // Sin interceptarla, el 429 de rate limiting devuelve el mensaje en
        // ingles de Laravel ("Too Many Attempts.") y, con APP_DEBUG=true,
        // filtra un stack trace completo -mismo problema que las tres
        // excepciones de arriba-.
        $exceptions->render(fn (ThrottleRequestsException $e, Request $request) => response()->json([
            'message' => 'Demasiados intentos. Intenta de nuevo en unos minutos.',
            'error' => 'demasiados_intentos',
        ], 429));
    })->create();
