<?php

namespace App\Notificaciones\Canales;

use App\Mail\CitaNotificacionMail;
use App\Notificaciones\Contracts\NotificacionServicio;
use App\Notificaciones\MensajeNotificacion;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailer as ConcreteMailer;
use Illuminate\Support\Facades\Mail;

/**
 * Canal de correo. En dev el mailer por defecto apunta a Mailpit (los correos
 * se capturan localmente sin salir a internet). Para produccion basta con
 * MAIL_MAILER=brevo + credenciales SMTP de Brevo en .env: este codigo no cambia.
 *
 * El envio ocurre DENTRO del job de cola, asi que no bloquea la respuesta de la
 * API. Si no hay email del paciente, el canal no hace nada (no es un error).
 */
class CorreoNotificacionServicio implements NotificacionServicio
{
    /**
     * @param  string|null  $mailer  mailer concreto a usar; null = el por defecto.
     */
    public function __construct(private readonly ?string $mailer = null) {}

    public function enviar(MensajeNotificacion $mensaje): void
    {
        if ($mensaje->pacienteEmail === null) {
            return;
        }

        $this->mailer()->to($mensaje->pacienteEmail)->send(new CitaNotificacionMail($mensaje));
    }

    public function canal(): string
    {
        return 'correo';
    }

    private function mailer(): Mailer|ConcreteMailer
    {
        return $this->mailer !== null ? Mail::mailer($this->mailer) : Mail::mailer();
    }
}
