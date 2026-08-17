<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Un staff no puede desactivarse ni quitarse a si mismo el rol/permiso de
 * gestion: evita que un admin se bloquee accidentalmente a si mismo.
 */
class OperacionSobreSiMismoException extends RuntimeException
{
    public function __construct(string $message = 'No podes realizar esta accion sobre tu propia cuenta.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'operacion_sobre_si_mismo',
        ], 422);
    }
}
