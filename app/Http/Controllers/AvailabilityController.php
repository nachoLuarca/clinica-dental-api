<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilityRequest;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

/**
 * Consulta de disponibilidad (slots libres) por profesional + tratamiento +
 * fecha. Compartido por ambos guards (staff y paciente): el tenant sale del
 * usuario autenticado por el middleware 'tenant', asi que el mismo endpoint
 * sirve a los dos frontends sin exponer datos de otras clinicas.
 */
class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $service) {}

    public function index(AvailabilityRequest $request): JsonResponse
    {
        $data = $request->filled('professional_id')
            ? $this->service->forProfessional(
                (int) $request->integer('professional_id'),
                (int) $request->integer('treatment_id'),
                (string) $request->string('fecha'),
            )
            : $this->service->forTenant(
                (int) $request->integer('treatment_id'),
                (string) $request->string('fecha'),
            );

        return response()->json(['data' => $data]);
    }
}
