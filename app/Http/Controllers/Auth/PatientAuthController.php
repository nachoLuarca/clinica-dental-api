<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PatientLoginRequest;
use App\Http\Requests\Auth\PatientRegisterRequest;
use App\Services\Auth\AuthResult;
use App\Services\Auth\PatientAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de autenticacion del PACIENTE (guard 'paciente').
 *
 * Igual que el de staff: orquesta y delega en PatientAuthService.
 */
class PatientAuthController extends Controller
{
    public function __construct(private readonly PatientAuthService $auth) {}

    public function register(PatientRegisterRequest $request): JsonResponse
    {
        return $this->respond($this->auth->register($request->validated()), 201);
    }

    public function login(PatientLoginRequest $request): JsonResponse
    {
        return $this->respond($this->auth->login($request->validated()), 200);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada.']);
    }

    private function respond(AuthResult $result, int $status): JsonResponse
    {
        return response()->json([
            'token' => $result->token,
            'token_type' => 'Bearer',
            'data' => $result->user,
        ], $status);
    }
}
