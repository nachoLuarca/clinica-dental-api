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
        $fecha = (string) $request->string('fecha');
        $sucursalId = $request->filled('sucursal_id') ? (int) $request->integer('sucursal_id') : null;

        if ($request->filled('professional_id')) {
            // Profesional puntual: treatment_id es obligatorio aca (ver
            // AvailabilityRequest), sin ambiguedad de duracion posible.
            $data = $this->service->forProfessional(
                (int) $request->integer('professional_id'),
                (int) $request->integer('treatment_id'),
                $fecha,
            );
        } elseif ($request->filled('treatment_id')) {
            $data = $this->service->forTenant((int) $request->integer('treatment_id'), $fecha, $sucursalId);
        } else {
            // Sin professional_id ni treatment_id: entry point Especialidad
            // del wizard, sin tratamiento puntual todavia.
            $data = $this->service->forTenantPorEspecialidad((int) $request->integer('especialidad_id'), $fecha, $sucursalId);
        }

        return response()->json(['data' => $data]);
    }
}
