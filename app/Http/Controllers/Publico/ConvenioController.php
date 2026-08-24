<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\ConvenioService;
use Illuminate\Http\JsonResponse;

/**
 * Listado PUBLICO de convenios activos (sin login), para la seccion
 * informativa del sitio del paciente.
 */
class ConvenioController extends Controller
{
    public function __construct(private readonly ConvenioService $service) {}

    public function index(): JsonResponse
    {
        $data = $this->service->catalogoPublico()->map(fn ($convenio) => [
            'id' => $convenio->id,
            'nombre' => $convenio->nombre,
            'tipo' => $convenio->tipo,
            'logo_url' => $convenio->logo_url,
            'descripcion' => $convenio->descripcion,
        ])->values();

        return response()->json(['data' => $data]);
    }
}
