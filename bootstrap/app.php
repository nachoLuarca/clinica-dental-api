<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
