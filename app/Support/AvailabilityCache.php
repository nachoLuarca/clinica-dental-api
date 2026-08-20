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
 *    solo se invalida por evento, como pide la arquitectura.
 *  - Cada entrada lleva DOS tags: uno por tenant+profesional+fecha (para
 *    invalidar UNA fecha puntual al crear/cancelar una cita) y otro por
 *    tenant+profesional a secas (para invalidar TODAS las fechas cacheadas
 *    de ese profesional de una vez, al editar su horario semanal -sin esto,
 *    un profesional editable/mantenible en el papel seguiria mostrando la
 *    grilla vieja en cualquier fecha ya consultada antes del cambio-).
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

    /**
     * Invalida TODA la disponibilidad cacheada de un profesional, sin
     * importar la fecha. Se dispara al editar su horario semanal
     * (ProfessionalService::update): un tramo agregado/borrado/movido
     * cambia la grilla de cualquier fecha futura, no solo una.
     */
    public function forgetForProfessional(int $tenantId, int $professionalId): void
    {
        Cache::tags([$this->tagProfessional($tenantId, $professionalId)])->flush();
    }

    private function tagged(int $tenantId, int $professionalId, string $fecha): CacheRepository
    {
        return Cache::tags([
            $this->tag($tenantId, $professionalId, $fecha),
            $this->tagProfessional($tenantId, $professionalId),
        ]);
    }

    private function tag(int $tenantId, int $professionalId, string $fecha): string
    {
        return "disponibilidad:t{$tenantId}:p{$professionalId}:{$fecha}";
    }

    private function tagProfessional(int $tenantId, int $professionalId): string
    {
        return "disponibilidad:t{$tenantId}:p{$professionalId}";
    }

    private function key(int $tenantId, int $professionalId, string $fecha, int $duracion): string
    {
        return "disponibilidad:t{$tenantId}:p{$professionalId}:{$fecha}:d{$duracion}";
    }
}
