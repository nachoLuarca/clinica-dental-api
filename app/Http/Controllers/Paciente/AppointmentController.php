<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paciente\AppointmentStoreRequest;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion de las citas del PROPIO paciente autenticado (guard 'paciente').
 *
 * Aislamiento reforzado: el paciente solo lista/cancela SUS citas. El id del
 * paciente se toma del usuario autenticado ($request->user()->id), nunca del
 * input, igual que el tenant_id.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginateForPatient(
                (int) $request->user()->id,
                $this->perPage($request),
            )
        );
    }

    public function store(AppointmentStoreRequest $request): JsonResponse
    {
        $appointment = $this->service->create(
            $request->validated(),
            (int) $request->user()->id,
        );

        return response()->json(['data' => $appointment], 201);
    }

    public function show(Request $request, int $appointment): JsonResponse
    {
        return response()->json([
            'data' => $this->service->find($appointment, (int) $request->user()->id),
        ]);
    }

    public function destroy(Request $request, int $appointment): JsonResponse
    {
        return response()->json([
            'data' => $this->service->cancel($appointment, (int) $request->user()->id),
        ]);
    }
}
