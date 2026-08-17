<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\TenantUpdateRequest;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

/**
 * Datos de marca de la propia clinica (nombre, logo, color). El controller
 * solo orquesta; el tenant siempre sale del TenantContext (nunca de la URL),
 * asi que no hay {tenant} en la ruta ni forma de leer/editar otra clinica.
 */
class TenantController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->service->current()]);
    }

    public function update(TenantUpdateRequest $request): JsonResponse
    {
        $data = $request->safe()->except('logo');

        return response()->json([
            'data' => $this->service->update($data, $request->file('logo')),
        ]);
    }
}
