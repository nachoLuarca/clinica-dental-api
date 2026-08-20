<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Http\Requests\Publico\PatientRegistroRequest;
use App\Http\Requests\Publico\VerificarRutRequest;
use App\Services\Publico\PatientRegistroService;
use Illuminate\Http\JsonResponse;

/**
 * Paso de Identificacion por RUT del flujo de reserva publico: sin login,
 * sin password, sin token de sesion (eso queda para una etapa futura del
 * proyecto). Solo confirma si el RUT ya es paciente y, si no, lo da de alta.
 */
class PatientRegistroController extends Controller
{
    public function __construct(private readonly PatientRegistroService $service) {}

    public function verificarRut(VerificarRutRequest $request): JsonResponse
    {
        $existe = $this->service->existeRut(
            $request->validated('rut'),
            $request->validated('turnstile_token'),
            $request->ip(),
        );

        return response()->json(['data' => ['existe' => $existe]]);
    }

    public function store(PatientRegistroRequest $request): JsonResponse
    {
        $patient = $this->service->registrar($request->validated());

        return response()->json([
            'data' => [
                'id' => $patient->id,
                'nombre' => $patient->nombre,
                'apellido' => $patient->apellido,
                'rut' => $patient->rut,
            ],
        ], 201);
    }
}
