<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PatientStoreRequest;
use App\Http\Requests\Staff\PatientUpdateRequest;
use App\Services\PatientRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro clinico de pacientes por el staff (guard 'staff'). Distinto del
 * PatientAuthController (login del propio paciente).
 */
class PatientController extends Controller
{
    public function __construct(private readonly PatientRegistryService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate(
                $this->perPage($request),
                $request->filled('search') ? (string) $request->input('search') : null,
            )
        );
    }

    public function store(PatientStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function show(int $patient): JsonResponse
    {
        return response()->json(['data' => $this->service->find($patient)]);
    }

    public function update(PatientUpdateRequest $request, int $patient): JsonResponse
    {
        return response()->json(['data' => $this->service->update($patient, $request->validated())]);
    }

    public function destroy(int $patient): JsonResponse
    {
        $this->service->delete($patient);

        return response()->json(null, 204);
    }
}
