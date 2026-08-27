<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\AppointmentStoreRequest;
use App\Http\Requests\Publico\PatientLookupRequest;
use App\Services\AppointmentService;
use App\Services\Publico\PatientLookupService;
use App\Services\Publico\PatientRegistroService;
use Illuminate\Http\JsonResponse;

/**
 * Gestion PUBLICA (sin login) de las citas del paciente.
 *
 * Dos formas de identificar al paciente segun la accion, a proposito:
 *  - index/destroy (consultar/cancelar): RUT + fecha de nacimiento
 *    (PatientLookupService), el segundo factor historico de esta gestion.
 *  - store (crear, paso Confirmar del wizard de reserva): RUT + Turnstile
 *    (PatientRegistroService::resolverIdentificado), mismo mecanismo que el
 *    paso de Identificacion -no se le pide fecha_nacimiento al paciente de
 *    nuevo si ya paso por ese paso sin que se la hayan pedido-.
 *
 * store() reutiliza el mismo AppointmentService que el paciente autenticado
 * (Paciente\AppointmentController): mismo bloqueo optimista, mismo 409 si el
 * horario se lo gano otro justo antes.
 */
class PatientAppointmentController extends Controller
{
    public function __construct(
        private readonly PatientLookupService $lookup,
        private readonly PatientRegistroService $registro,
        private readonly AppointmentService $appointments,
    ) {}

    public function index(PatientLookupRequest $request): JsonResponse
    {
        $patient = $this->lookup->resolver($request->validated('rut'), $request->validated('fecha_nacimiento'));

        return $this->paginatedResponse(
            $this->appointments->paginateForPatient($patient->id, $this->perPage($request))
        );
    }

    public function store(AppointmentStoreRequest $request): JsonResponse
    {
        $patient = $this->registro->resolverIdentificado(
            $request->validated('rut'),
            $request->validated('turnstile_token'),
            $request->ip(),
        );

        $appointment = $this->appointments->create($request->validated(), $patient->id);

        return response()->json(['data' => $appointment], 201);
    }

    public function destroy(PatientLookupRequest $request, int $appointment): JsonResponse
    {
        $patient = $this->lookup->resolver($request->validated('rut'), $request->validated('fecha_nacimiento'));

        return response()->json([
            'data' => $this->appointments->cancel($appointment, $patient->id),
        ]);
    }
}
