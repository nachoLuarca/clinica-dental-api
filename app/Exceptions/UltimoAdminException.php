<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * La operacion dejaria a la clinica sin NINGUN staff activo capaz de
 * gestionar roles/usuarios (ver RoleProvisioner::PERMISO_GESTION).
 */
class UltimoAdminException extends RuntimeException
{
    public function __construct(
        string $message = 'Esta accion dejaria a la clinica sin nadie que pueda gestionar roles o usuarios.',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'ultimo_admin',
        ], 422);
    }
}
