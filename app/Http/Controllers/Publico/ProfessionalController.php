<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use Illuminate\Http\JsonResponse;

/**
 * Listado PUBLICO de profesionales activos (sin login): alimenta el paso del
 * wizard de reserva donde el paciente elige profesional, o confirma que
 * existen profesionales antes de reservar en modo "cualquiera disponible".
 * Solo datos de presentacion -nunca el email interno del profesional-.
 */
class ProfessionalController extends Controller
{
    public function __construct(private readonly ProfessionalRepositoryInterface $professionals) {}

    public function index(): JsonResponse
    {
        $data = $this->professionals->allActivos()->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'apellido' => $p->apellido,
            'especialidad' => $p->especialidad,
        ])->values();

        return response()->json(['data' => $data]);
    }
}
