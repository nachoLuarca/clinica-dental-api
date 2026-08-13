<?php

/*
|--------------------------------------------------------------------------
| Configuracion de CORS
|--------------------------------------------------------------------------
|
| Los origenes permitidos se leen de la variable de entorno
| CORS_ALLOWED_ORIGINS (lista separada por comas). NUNCA se usa '*': solo se
| habilitan los dos frontends del sistema (clinica-dental-portal y
| clinica-dental-paciente).
|
| En desarrollo, esos frontends corren en local con Vite; por eso el default
| apunta a los puertos tipicos de Vite en localhost. En produccion se ponen
| los dominios reales en el .env, sin tocar codigo.
|
| Ejemplo .env:
|   CORS_ALLOWED_ORIGINS=https://portal.miclinica.com,https://reservas.miclinica.com
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:5174')),
)));

return [

    // Solo la API (y el health check) participan de CORS; no hay vistas web.
    'paths' => ['api/*', 'up'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Acotado por .env. Si la lista queda vacia, no se permite ningun origen
    // cruzado (fail-closed), nunca '*'.
    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    // Headers que los frontends necesitan enviar (auth, JSON, tenant publico).
    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-Clinica',
    ],

    'exposed_headers' => [],

    'max_age' => 0,

    // Sanctum en este proyecto usa tokens Bearer (no cookies de sesion), asi
    // que no se necesitan credenciales cross-site.
    'supports_credentials' => false,

];
