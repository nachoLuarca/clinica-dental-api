<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Limites de rate limiting (peticiones por minuto)
    |--------------------------------------------------------------------------
    |
    | Todos configurables por .env para poder apretar/aflojar sin tocar codigo.
    | Aplican a los named limiters definidos en AppServiceProvider:
    |   - login:        intentos de inicio de sesion (por clinica+email+IP).
    |   - register:     creacion de cuentas (por clinica+IP).
    |   - availability: disponibilidad para usuarios autenticados (por usuario/IP).
    |   - publico:      endpoints publicos catalogo/disponibilidad (por tenant+IP).
    |
    */

    'rate_limits' => [
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
        'register' => (int) env('RATE_LIMIT_REGISTER', 10),
        'availability' => (int) env('RATE_LIMIT_AVAILABILITY', 60),
        'publico' => (int) env('RATE_LIMIT_PUBLICO', 30),
    ],

];
