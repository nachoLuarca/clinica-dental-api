<?php

namespace App\Mail;

use App\Notificaciones\MensajeNotificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de notificacion de cita (confirmacion, recordatorio o cancelacion).
 *
 * Recibe un MensajeNotificacion ya sanitizado; solo elige asunto/plantilla
 * segun el tipo. El encolado del envio lo maneja el Job que despacha el canal,
 * por eso este Mailable no implementa ShouldQueue (evita doble encolado).
 */
class CitaNotificacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly MensajeNotificacion $mensaje) {}

    public function envelope(): Envelope
    {
        $asuntos = [
            MensajeNotificacion::TIPO_CONFIRMACION => 'Confirmacion de tu cita',
            MensajeNotificacion::TIPO_RECORDATORIO => 'Recordatorio de tu cita',
            MensajeNotificacion::TIPO_CANCELACION => 'Tu cita fue cancelada',
        ];

        $asunto = $asuntos[$this->mensaje->tipo] ?? 'Notificacion de tu cita';

        return new Envelope(subject: $asunto.' - '.$this->mensaje->clinicaNombre);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cita',
            with: ['mensaje' => $this->mensaje],
        );
    }
}
