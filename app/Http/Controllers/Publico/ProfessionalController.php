<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Models\Treatment;
use App\Repositories\Contracts\ProfessionalRepositoryInterface;
use App\Repositories\Contracts\TreatmentRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listado PUBLICO de profesionales activos (sin login): alimenta el paso del
 * wizard de reserva donde el paciente elige profesional, o confirma que
 * existen profesionales antes de reservar en modo "cualquiera disponible".
 * Solo datos de presentacion -nunca el email interno del profesional-.
 *
 * Con ?treatment_id=, filtra a los profesionales elegibles para la
 * especialidad de ese tratamiento (paso 11/12: FK real Treatment::especialidad_id),
 * para que el paso "elegir profesional" del wizard ya venga acotado por el
 * tratamiento elegido antes.
 */
class ProfessionalController extends Controller
{
    public function __construct(
        private readonly ProfessionalRepositoryInterface $professionals,
        private readonly TreatmentRepositoryInterface $treatments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $especialidadId = null;

        if ($request->filled('treatment_id')) {
            $treatment = $this->treatments->find((int) $request->input('treatment_id'))
                ?? throw (new ModelNotFoundException)->setModel(Treatment::class);

            $especialidadId = $treatment->especialidad_id;
        }

        $data = $this->professionals->allActivosParaEspecialidad($especialidadId)->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'apellido' => $p->apellido,
            'especialidad' => $p->especialidad,
        ])->values();

        return response()->json(['data' => $data]);
    }
}
