<?php

namespace App\Http\Controllers\Publico;

use App\Http\Controllers\Controller;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;

/**
 * Marca de la clinica para el sitio PUBLICO de pacientes (sin login): nombre,
 * logo y color, para que cada clinica se vea con su propia identidad. Mismo
 * TenantService que usa el staff (App\Http\Controllers\Staff\TenantController)
 * -no depende de auth, solo del TenantContext ya fijado por 'tenant.publico'-,
 * pero acotado a los campos de marca: nunca expone 'activo' ni el 'slug'
 * (interno) a un cliente sin autenticar.
 */
class TenantController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function show(): JsonResponse
    {
        $tenant = $this->service->current();

        return response()->json(['data' => [
            'nombre' => $tenant->nombre,
            'logo_url' => $tenant->logo_url,
            'color_primario' => $tenant->color_primario,
            // Blanco o negro, el que de mejor contraste sobre color_primario
            // (WCAG): asi el frontend no corre riesgo de texto ilegible si una
            // clinica elige un color primario claro para sus botones.
            'color_contraste' => $tenant->color_contraste,
        ]]);
    }
}
