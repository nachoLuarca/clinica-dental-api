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
 * best-effort con reintentos: un fallo transitorio del canal (ej. WhatsApp
 * caido un segundo) se reintenta con backoff ANTES de darlo por perdido,
 * segun 'notificaciones.reintentos'. Solo si se agotan los intentos se loguea
 * y se descarta, sin re-lanzar. El reintento es manual (no el del job de la
 * cola) para que el comportamiento sea identico con cualquier driver,
 * incluido 'sync' (tests): la excepcion nunca escapa hacia la respuesta de la
 * API ni marca el job como fallido por un problema transitorio del canal.
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
        $intentosMaximos = max(1, (int) config('notificaciones.reintentos.intentos', 3));
        $backoffMs = max(0, (int) config('notificaciones.reintentos.backoff_ms', 300));

        for ($intento = 1; $intento <= $intentosMaximos; $intento++) {
            try {
                $manager->resolver($this->canal)->enviar($this->mensaje);

                return;
            } catch (Throwable $e) {
                $ultimoIntento = $intento === $intentosMaximos;

                Log::warning('Fallo el envio de notificacion de cita (best-effort).', [
                    'canal' => $this->canal,
                    'tipo' => $this->mensaje->tipo,
                    'error' => $e->getMessage(),
                    'intento' => $intento,
                    'intentos_maximos' => $intentosMaximos,
                    'descartada' => $ultimoIntento,
                ]);

                if (! $ultimoIntento && $backoffMs > 0) {
                    usleep($backoffMs * 1000);
                }
            }
        }
    }
}
