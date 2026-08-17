<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\PatientLookupRequest;
use App\Services\AppointmentService;
use App\Services\Publico\PatientLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion PUBLICA (sin login) de las citas del paciente: identificado por
 * RUT + fecha de nacimiento en vez de un token Sanctum. Reutiliza el mismo
 * AppointmentService que el paciente autenticado (Paciente\AppointmentController),
 * pasandole el patient_id ya resuelto -nunca uno que mande el cliente-.
 */
class PatientAppointmentController extends Controller
{
    public function __construct(
        private readonly PatientLookupService $lookup,
        private readonly AppointmentService $appointments,
    ) {}

    public function index(PatientLookupRequest $request): JsonResponse
    {
        $patient = $this->lookup->resolver($request->validated('rut'), $request->validated('fecha_nacimiento'));

        return $this->paginatedResponse(
            $this->appointments->paginateForPatient($patient->id, (int) $request->integer('per_page', 15))
        );
    }

    public function destroy(PatientLookupRequest $request, int $appointment): JsonResponse
    {
        $patient = $this->lookup->resolver($request->validated('rut'), $request->validated('fecha_nacimiento'));

        return response()->json([
            'data' => $this->appointments->cancel($appointment, $patient->id),
        ]);
    }
}
