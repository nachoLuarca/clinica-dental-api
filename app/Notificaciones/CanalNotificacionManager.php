<?php

namespace App\Notificaciones;

use App\Notificaciones\Contracts\NotificacionServicio;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resuelve canales de notificacion por su clave.
 *
 * El job de cola guarda solo la CLAVE del canal ('correo'/'whatsapp'), no la
 * instancia, y aqui la reconstruye desde el contenedor. Centralizar la lista de
 * canales activos y su resolucion permite agregar/quitar un canal (o cambiar su
 * implementacion) en un solo lugar.
 */
class CanalNotificacionManager
{
    public function __construct(private readonly Container $app) {}

    /**
     * Claves de los canales activos (config/notificaciones.php).
     *
     * @return array<int, string>
     */
    public function activos(): array
    {
        return config('notificaciones.canales', []);
    }

    /**
     * Instancia el canal concreto para una clave. La implementacion se resuelve
     * del contenedor (cada canal esta bindeado en AppServiceProvider).
     */
    public function resolver(string $clave): NotificacionServicio
    {
        return match ($clave) {
            'correo' => $this->app->make(Canales\CorreoNotificacionServicio::class),
            'whatsapp' => $this->app->make(Canales\WhatsAppNotificacionServicio::class),
            default => throw new InvalidArgumentException("Canal de notificacion desconocido: {$clave}"),
        };
    }
}
