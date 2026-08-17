<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * El rol 'admin' (App\Services\Auth\RoleProvisioner::ROL_PROTEGIDO) no se
 * puede renombrar, editar sus permisos ni borrar: es la garantia minima de
 * que la clinica siempre tiene con que gestionarse a si misma.
 */
class RolProtegidoException extends RuntimeException
{
    public function __construct(string $message = "El rol 'admin' no se puede editar ni eliminar.")
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'rol_protegido',
        ], 403);
    }
}
