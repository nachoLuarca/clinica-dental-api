<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UserEstadoRequest;
use App\Http\Requests\Staff\UserPasswordRequest;
use App\Http\Requests\Staff\UserRolRequest;
use App\Http\Requests\Staff\UserStoreRequest;
use App\Http\Requests\Staff\UserUpdateRequest;
use App\Services\Auth\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gestion del staff de la propia clinica (guard 'staff'). Distinto del
 * StaffAuthController (login/registro/me propios). El controller solo
 * orquesta; toda la logica y las salvaguardas viven en el servicio.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'rol' => $request->string('rol')->toString() ?: null,
            'activo' => $request->has('activo') ? $request->boolean('activo') : null,
            'nombre' => $request->string('nombre')->toString() ?: null,
        ], fn ($v) => $v !== null);

        return $this->paginatedResponse(
            $this->service->paginate($filters, $this->perPage($request))
        );
    }

    public function show(int $user): JsonResponse
    {
        return response()->json(['data' => $this->service->find($user)]);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function update(UserUpdateRequest $request, int $user): JsonResponse
    {
        return response()->json(['data' => $this->service->update($user, $request->validated())]);
    }

    public function actualizarRol(UserRolRequest $request, int $user): JsonResponse
    {
        return response()->json([
            'data' => $this->service->asignarRol($user, $request->validated('rol'), $request->user()),
        ]);
    }

    public function actualizarEstado(UserEstadoRequest $request, int $user): JsonResponse
    {
        return response()->json([
            'data' => $this->service->setEstado($user, $request->boolean('activo'), $request->user()),
        ]);
    }

    public function resetearPassword(UserPasswordRequest $request, int $user): JsonResponse
    {
        $this->service->resetearPassword($user, $request->validated('password'));

        return response()->json(['message' => 'Password actualizada. Se cerraron todas las sesiones de este usuario.']);
    }
}
