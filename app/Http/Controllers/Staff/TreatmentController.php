<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\TreatmentStoreRequest;
use App\Http\Requests\Staff\TreatmentUpdateRequest;
use App\Services\TreatmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD del catalogo de tratamientos/servicios (guard 'staff').
 */
class TreatmentController extends Controller
{
    public function __construct(private readonly TreatmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate($this->perPage($request))
        );
    }

    public function store(TreatmentStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function show(int $treatment): JsonResponse
    {
        return response()->json(['data' => $this->service->find($treatment)]);
    }

    public function update(TreatmentUpdateRequest $request, int $treatment): JsonResponse
    {
        return response()->json(['data' => $this->service->update($treatment, $request->validated())]);
    }

    public function destroy(int $treatment): JsonResponse
    {
        $this->service->delete($treatment);

        return response()->json(null, 204);
    }
}
