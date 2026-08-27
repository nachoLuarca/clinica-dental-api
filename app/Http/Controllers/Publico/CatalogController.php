<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\TreatmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogo PUBLICO de tratamientos/servicios que consume el sitio de pacientes
 * (clinica-dental-paciente) para mostrar la oferta de la clinica sin requerir
 * login.
 *
 * El tenant lo fija el middleware 'tenant.publico' a partir del slug de clinica
 * del header 'X-Clinica'; por eso el mismo TreatmentService (ya filtrado por
 * TenantScope) devuelve solo los tratamientos de esa clinica. Es de solo
 * lectura: no expone crear/editar/borrar.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly TreatmentService $service) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(
            $this->service->paginate($this->perPage($request))
        );
    }
}
