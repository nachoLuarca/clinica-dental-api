<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;

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
    })->create();
