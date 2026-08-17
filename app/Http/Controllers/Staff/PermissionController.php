<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

/**
 * Catalogo de permisos disponibles (solo lectura), agrupado por recurso.
 * Alimenta la matriz de permisos del frontend al crear/editar un rol. Los
 * permisos son globales (no por tenant): cualquier clinica puede usarlos
 * todos en sus propios roles.
 */
class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $agrupado = Permission::query()
            ->where('guard_name', 'staff')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $p) => str_contains($p->name, '.') ? explode('.', $p->name, 2)[0] : 'general')
            ->map(fn ($grupo) => $grupo->pluck('name')->values())
            ->sortKeys();

        return response()->json(['data' => $agrupado]);
    }
}
