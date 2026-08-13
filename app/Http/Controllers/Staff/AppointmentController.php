<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\AppointmentStoreRequest;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion de citas desde el portal staff (guard 'staff'). El staff reserva para
 * cualquier paciente de SU clinica y ve/cancela las citas del tenant. Solo
 * orquesta; toda la logica vive en AppointmentService.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function store(AppointmentStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function show(int $appointment): JsonResponse
    {
        return response()->json(['data' => $this->service->find($appointment)]);
    }

    public function destroy(int $appointment): JsonResponse
    {
        return response()->json(['data' => $this->service->cancel($appointment)]);
    }
}
