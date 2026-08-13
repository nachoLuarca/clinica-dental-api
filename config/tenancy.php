<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Columna de tenant
    |--------------------------------------------------------------------------
    |
    | Nombre de la columna que identifica al tenant en las tablas que usan
    | el trait BelongsToTenant.
    |
    */
    'tenant_column' => 'tenant_id',

    /*
    |--------------------------------------------------------------------------
    | Guards que resuelven el tenant
    |--------------------------------------------------------------------------
    |
    | El middleware ResolveTenant recorre estos guards en orden y toma el
    | tenant del PRIMER usuario autenticado que encuentre. El tenant SIEMPRE
    | sale del usuario autenticado, nunca de un valor enviado por el frontend.
    |
    | Punto de extension: en el paso 3 (auth) se agregaran aqui los guards
    | 'staff' y 'paciente'. Solo se consideran guards que existan realmente en
    | config('auth.guards'), asi que se puede dejar la lista completa desde ya.
    |
    */
    'guards' => ['staff', 'paciente', 'web', 'sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Header de tenant para endpoints publicos
    |--------------------------------------------------------------------------
    |
    | En los endpoints publicos (catalogo/disponibilidad del sitio de pacientes)
    | no hay usuario autenticado, asi que el frontend indica QUE clinica esta
    | mostrando mediante este header, que contiene el slug de la clinica. El
    | middleware ResolvePublicTenant lo valida contra la tabla de tenants: el id
    | interno del tenant nunca viaja por el cliente.
    |
    */
    'public_header' => env('TENANT_PUBLIC_HEADER', 'X-Clinica'),

];
