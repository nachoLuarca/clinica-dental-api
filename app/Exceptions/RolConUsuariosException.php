<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * No se puede borrar un rol que todavia tiene staff asignado: hay que
 * reasignarlos a otro rol primero.
 */
class RolConUsuariosException extends RuntimeException
{
    public function __construct(string $message = 'No se puede eliminar un rol con staff asignado. Reasigna el staff a otro rol primero.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'rol_con_usuarios',
        ], 422);
    }
}
