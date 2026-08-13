<?php

namespace App\Jobs;

use App\Notificaciones\CanalNotificacionManager;
use App\Notificaciones\MensajeNotificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envia UNA notificacion de cita por UN canal concreto.
 *
 * Se despacha un job por canal (correo, whatsapp), de modo que cada canal es
 * un trabajo de cola independiente: si el de WhatsApp falla, el de correo corre
 * igual. La cita ya esta persistida antes de encolar, asi que una notificacion
 * nunca puede tumbar la reserva.
 *
 * best-effort: cualquier error del canal se captura y se loguea, no se re-lanza.
 * Asi, incluso con el driver 'sync' (tests), un canal caido jamas propaga la
 * excepcion hasta la respuesta de la API. En produccion, con cola async, esto
 * ademas evita marcar el job como fallido por un problema transitorio del canal.
 */
class EnviarNotificacionCita implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly MensajeNotificacion $mensaje,
        public readonly string $canal,
    ) {}

    public function handle(CanalNotificacionManager $manager): void
    {
        try {
            $manager->resolver($this->canal)->enviar($this->mensaje);
        } catch (Throwable $e) {
            Log::warning('Fallo el envio de notificacion de cita (best-effort).', [
                'canal' => $this->canal,
                'tipo' => $this->mensaje->tipo,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
