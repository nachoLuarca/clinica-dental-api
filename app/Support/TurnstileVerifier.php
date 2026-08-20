<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Valida server-side el token de Cloudflare Turnstile que manda el
 * frontend (verificacion humana del paso de Identificacion por RUT del
 * flujo de reserva publico).
 *
 * Sin esta validacion en el backend, el widget de Turnstile en el frontend
 * es solo cosmetico: cualquiera podria pegarle directo al endpoint sin
 * resolver el challenge. El secret SOLO vive en la variable de entorno
 * TURNSTILE_SECRET_KEY del servidor (config('services.turnstile.secret')),
 * nunca en el codigo.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Falla CERRADO (rechaza) ante cualquier problema: secret sin
     * configurar, token vacio, error de red hacia Cloudflare, o respuesta
     * que indique que el challenge no se resolvio. Es un control de
     * seguridad, no una notificacion best-effort: no hay "dejar pasar por
     * las dudas".
     */
    public function verificar(?string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret');

        if (empty($secret) || empty($token)) {
            return false;
        }

        try {
            $respuesta = Http::asForm()->post(self::VERIFY_URL, array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]));

            return $respuesta->successful() && ($respuesta->json('success') === true);
        } catch (\Throwable $e) {
            Log::warning('No se pudo validar el token de Turnstile.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
