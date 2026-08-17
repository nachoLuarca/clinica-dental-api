<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Requests\Auth\StaffRegisterRequest;
use App\Models\User;
use App\Services\Auth\AuthResult;
use App\Services\Auth\StaffAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

/**
 * Endpoints de autenticacion del STAFF (guard 'staff').
 *
 * El controller solo orquesta: delega toda la logica al StaffAuthService y
 * nunca toca Eloquent ni la base directamente.
 */
class StaffAuthController extends Controller
{
    public function __construct(private readonly StaffAuthService $auth) {}

    public function register(StaffRegisterRequest $request): JsonResponse
    {
        return $this->respond($this->auth->register($request->validated()), 201);
    }

    public function login(StaffLoginRequest $request): JsonResponse
    {
        return $this->respond($this->auth->login($request->validated()), 200);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->withRoles($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada.']);
    }

    private function respond(AuthResult $result, int $status): JsonResponse
    {
        /** @var User $user */
        $user = $result->user;

        return response()->json([
            'token' => $result->token,
            'token_type' => 'Bearer',
            'data' => $this->withRoles($user),
        ], $status);
    }

    /**
     * El frontend necesita el rol para ocultar acciones de antemano (no solo
     * reaccionar a un 403 despues de intentarlas). No se agrega como $appends
     * en el modelo para no pisar la relacion roles() que usa Spatie
     * internamente (assignRole/syncRoles).
     *
     * login/register no pasan por el middleware 'tenant' (no hay sesion
     * previa), asi que el "team" activo de Spatie todavia no esta seteado:
     * hay que fijarlo a mano con el tenant del usuario antes de leer roles,
     * o getRoleNames() consulta con team_id null y devuelve vacio.
     *
     * @return array<string, mixed>
     */
    private function withRoles(User $user): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return [...$user->toArray(), 'roles' => $user->getRoleNames()->all()];
    }
}
