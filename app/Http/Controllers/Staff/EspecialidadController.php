<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\EspecialidadStoreRequest;
use App\Http\Requests\Staff\EspecialidadUpdateRequest;
use App\Services\EspecialidadService;
use Illuminate\Http\JsonResponse;

/**
 * CRUD del catalogo de especialidades (guard 'staff'). Sin paginar: es un
 * catalogo chico pensado para selects (ver tambien Professional::especialidades
 * y Especialidad::categorias para el mapeo a categorias de tratamiento).
 */
class EspecialidadController extends Controller
{
    public function __construct(private readonly EspecialidadService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->all()]);
    }

    public function store(EspecialidadStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function show(int $especialidad): JsonResponse
    {
        return response()->json(['data' => $this->service->find($especialidad)]);
    }

    public function update(EspecialidadUpdateRequest $request, int $especialidad): JsonResponse
    {
        return response()->json(['data' => $this->service->update($especialidad, $request->validated())]);
    }

    public function destroy(int $especialidad): JsonResponse
    {
        $this->service->delete($especialidad);

        return response()->json(null, 204);
    }
}
