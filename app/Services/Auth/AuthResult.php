<?php

namespace App\Services\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resultado de un registro/login: el usuario autenticado y su token Bearer
 * recien emitido. El controller lo traduce a la respuesta JSON.
 */
final class AuthResult
{
    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $token,
    ) {}
}
