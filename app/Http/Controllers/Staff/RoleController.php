<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RolePermisosRequest;
use App\Http\Requests\Staff\RoleStoreRequest;
use App\Http\Requests\Staff\RoleUpdateRequest;
use App\Services\Auth\RoleManagementService;
use Illuminate\Http\JsonResponse;

/**
 * Roles editables de la propia clinica (guard 'staff'). El rol 'admin' esta
 * protegido: no se puede renombrar, editarle los permisos ni borrarlo (ver
 * RoleProvisioner::ROL_PROTEGIDO).
 */
class RoleController extends Controller
{
    public function __construct(private readonly RoleManagementService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->service->all()->values()]);
    }

    public function show(int $role): JsonResponse
    {
        return response()->json(['data' => $this->service->find($role)]);
    }

    public function store(RoleStoreRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->create($request->validated('name'), $request->validated('permissions', [])),
        ], 201);
    }

    public function update(RoleUpdateRequest $request, int $role): JsonResponse
    {
        return response()->json(['data' => $this->service->update($role, $request->validated())]);
    }

    public function actualizarPermisos(RolePermisosRequest $request, int $role): JsonResponse
    {
        return response()->json([
            'data' => $this->service->updatePermisos($role, $request->validated('permissions')),
        ]);
    }

    public function destroy(int $role): JsonResponse
    {
        $this->service->delete($role);

        return response()->json(null, 204);
    }
}
