<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
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
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Alias de middleware del proyecto:
        //  - 'tenant': resuelve el tenant del usuario AUTENTICADO (nunca de input
        //    del cliente). Debe correr SIEMPRE despues de la autenticacion.
        //  - 'tenant.publico': resuelve el tenant por slug de clinica (header
        //    X-Clinica) para endpoints publicos sin sesion (catalogo/disponibilidad).
        //  - 'abilities'/'ability': verificacion de abilities del token Sanctum
        //    (defensa en profundidad sobre la separacion de guards staff/paciente).
        $middleware->alias([
            'tenant' => \App\Http\Middleware\ResolveTenant::class,
            'tenant.publico' => \App\Http\Middleware\ResolvePublicTenant::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
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
            'message' => 'No tenes permiso para realizar esta accion.',
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
    })->create();
