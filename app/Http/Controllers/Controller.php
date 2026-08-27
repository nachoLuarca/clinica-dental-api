<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Tamano de pagina pedido por el cliente (?per_page=), o el default de
     * config('paginacion.default_per_page') si no lo manda.
     */
    protected function perPage(Request $request): int
    {
        return (int) $request->integer('per_page', config('paginacion.default_per_page'));
    }

    /**
     * Normaliza un paginador de Laravel al shape { data, meta, links }, el mismo
     * que producen las API Resource Collections. Asi los listados exponen los
     * metadatos de paginacion de forma consistente para ambos frontends.
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
