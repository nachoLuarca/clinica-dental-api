<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\EspecialidadService;
use Illuminate\Http\JsonResponse;

/**
 * Catalogo PUBLICO de especialidades (sin login): cada especialidad con sus
 * tratamientos activos y la cantidad de profesionales activos vinculados, ya
 * armado por el backend. Reemplaza lo que el frontend armaba a mano (paso
 * "Tratamiento" del wizard de reserva y el catalogo publico) pidiendo
 * /publico/profesionales?treatment_id= por cada especialidad.
 *
 * El tenant lo fija el middleware 'tenant.publico' a partir del slug de
 * clinica del header 'X-Clinica'; por eso el mismo EspecialidadService (ya
 * filtrado por TenantScope) devuelve solo las especialidades de esa clinica.
 * Es de solo lectura: no expone crear/editar/borrar (eso es Staff\EspecialidadController).
 */
class EspecialidadController extends Controller
{
    public function __construct(private readonly EspecialidadService $service) {}

    public function index(): JsonResponse
    {
        // Forma explicita (no el modelo crudo): la relacion se llama
        // 'treatments' en el codigo (ingles, consistente con el resto del
        // modelo), pero el contrato publico expone 'tratamientos' en
        // espanol -mismo criterio que 'profesionales_count' en el repo-.
        $data = $this->service->catalogoPublico()->map(fn ($especialidad) => [
            'id' => $especialidad->id,
            'nombre' => $especialidad->nombre,
            'profesionales_count' => $especialidad->profesionales_count,
            'tratamientos' => $especialidad->treatments->map(fn ($tratamiento) => [
                'id' => $tratamiento->id,
                'nombre' => $tratamiento->nombre,
                'descripcion' => $tratamiento->descripcion,
                'precio' => $tratamiento->precio,
                'duracion_minutos' => $tratamiento->duracion_minutos,
                'slug' => $tratamiento->slug,
                'activo' => $tratamiento->activo,
            ])->values(),
        ])->values();

        return response()->json(['data' => $data]);
    }
}
