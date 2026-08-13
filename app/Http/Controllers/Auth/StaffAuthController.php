<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use App\Http\Requests\Auth\StaffRegisterRequest;
use App\Services\Auth\AuthResult;
use App\Services\Auth\StaffAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
