<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\SucursalService;
use Illuminate\Http\JsonResponse;

/**
 * Listado PUBLICO de sucursales activas (sin login): direccion, comuna,
 * telefono y horario de atencion, para la seccion informativa del sitio del
 * paciente.
 */
class SucursalController extends Controller
{
    public function __construct(private readonly SucursalService $service) {}

    public function index(): JsonResponse
    {
        $data = $this->service->catalogoPublico()->map(fn ($sucursal) => [
            'id' => $sucursal->id,
            'nombre' => $sucursal->nombre,
            'direccion' => $sucursal->direccion,
            'comuna' => $sucursal->comuna,
            'telefono' => $sucursal->telefono,
            'horarios' => $sucursal->horarios->map(fn ($horario) => [
                'dia_semana' => $horario->dia_semana,
                'hora_inicio' => $horario->hora_inicio,
                'hora_fin' => $horario->hora_fin,
            ])->values(),
        ])->values();

        return response()->json(['data' => $data]);
    }
}
