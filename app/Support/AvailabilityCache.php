<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Encapsula la cache de disponibilidad en Redis.
 *
 * Diseno de la clave e invalidacion:
 *  - El resultado se guarda SIN TTL (rememberForever): no expira por tiempo,
 *    solo se invalida por evento (crear/cancelar cita), como pide la arquitectura.
 *  - Se etiqueta por tenant + profesional + fecha. La invalidacion hace flush de
 *    ESA etiqueta, borrando de una sola vez todas las variantes por duracion de
 *    tratamiento de ese profesional/fecha (que comparten los mismos slots base).
 *  - La clave concreta incluye ademas la duracion, porque distintos tratamientos
 *    producen distinta grilla de slots sobre el mismo horario.
 *
 * Requiere un store que soporte tags (redis en dev, array en tests). El driver
 * de cache 'database'/'file' no sirve para esto.
 */
class AvailabilityCache
{
    /**
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T
     */
    public function remember(int $tenantId, int $professionalId, string $fecha, int $duracion, Closure $callback): mixed
    {
        return $this->tagged($tenantId, $professionalId, $fecha)
            ->rememberForever($this->key($tenantId, $professionalId, $fecha, $duracion), $callback);
    }

    /**
     * Invalida toda la disponibilidad cacheada de un profesional en una fecha.
     * Se dispara al crear o cancelar una cita de ese profesional/fecha.
     */
    public function forget(int $tenantId, int $professionalId, string $fecha): void
    {
        $this->tagged($tenantId, $professionalId, $fecha)->flush();
    }

    private function tagged(int $tenantId, int $professionalId, string $fecha): CacheRepository
    {
        return Cache::tags([$this->tag($tenantId, $professionalId, $fecha)]);
    }

    private function tag(int $tenantId, int $professionalId, string $fecha): string
    {
        return "disponibilidad:t{$tenantId}:p{$professionalId}:{$fecha}";
    }

    private function key(int $tenantId, int $professionalId, string $fecha, int $duracion): string
    {
        return "disponibilidad:t{$tenantId}:p{$professionalId}:{$fecha}:d{$duracion}";
    }
}
