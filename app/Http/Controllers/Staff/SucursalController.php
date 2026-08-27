<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SucursalStoreRequest;
use App\Http\Requests\Staff\SucursalUpdateRequest;
use App\Services\SucursalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de sucursales (sedes de la clinica, guard 'staff').
 */
class SucursalController extends Controller
{
    public function __construct(private readonly SucursalService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate($this->perPage($request))
        );
    }

    public function store(SucursalStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function show(int $sucursal): JsonResponse
    {
        return response()->json(['data' => $this->service->find($sucursal)]);
    }

    public function update(SucursalUpdateRequest $request, int $sucursal): JsonResponse
    {
        return response()->json(['data' => $this->service->update($sucursal, $request->validated())]);
    }

    public function destroy(int $sucursal): JsonResponse
    {
        $this->service->delete($sucursal);

        return response()->json(null, 204);
    }
}
