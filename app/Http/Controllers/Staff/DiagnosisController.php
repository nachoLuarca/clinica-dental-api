<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\DiagnosisStoreRequest;
use App\Http\Requests\Staff\DiagnosisUpdateRequest;
use App\Services\DiagnosisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Diagnosticos clinicos anidados bajo un paciente (guard 'staff').
 * Rutas: /staff/patients/{patient}/diagnoses[/{diagnosis}]
 */
class DiagnosisController extends Controller
{
    public function __construct(private readonly DiagnosisService $service) {}

    public function index(Request $request, int $patient): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginateForPatient($patient, $this->perPage($request))
        );
    }

    public function store(DiagnosisStoreRequest $request, int $patient): JsonResponse
    {
        return response()->json(['data' => $this->service->create($patient, $request->validated())], 201);
    }

    public function show(int $patient, int $diagnosis): JsonResponse
    {
        return response()->json(['data' => $this->service->find($patient, $diagnosis)]);
    }

    public function update(DiagnosisUpdateRequest $request, int $patient, int $diagnosis): JsonResponse
    {
        return response()->json(['data' => $this->service->update($patient, $diagnosis, $request->validated())]);
    }

    public function destroy(int $patient, int $diagnosis): JsonResponse
    {
        $this->service->delete($patient, $diagnosis);

        return response()->json(null, 204);
    }
}
