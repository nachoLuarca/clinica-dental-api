<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * El slot solicitado ya no esta disponible (choque de reserva).
 *
 * Se lanza tanto por la verificacion en tiempo real de disponibilidad como por
 * la violacion del indice unico parcial (bloqueo optimista). Se renderiza como
 * 409 Conflict con un cuerpo claro y accionable, para que el frontend pueda
 * ofrecer al usuario el siguiente horario libre en vez de mostrar un 500.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(
        string $message = 'El horario seleccionado ya no esta disponible. Por favor elige otro.',
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'slot_no_disponible',
        ], 409);
    }
}
