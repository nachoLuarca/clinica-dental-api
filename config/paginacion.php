<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tamano de pagina por defecto
    |--------------------------------------------------------------------------
    |
    | Usado por todos los listados paginados de la API cuando el cliente no
    | manda ?per_page=. Antes vivia repetido como literal "15" en cada
    | controller; ahora es un solo valor, configurable por .env sin tocar
    | codigo.
    |
    */

    'default_per_page' => (int) env('PAGINACION_POR_DEFECTO', 15),

];
