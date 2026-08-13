<?php

namespace App\Notificaciones\Canales;

use App\Notificaciones\Contracts\NotificacionServicio;
use App\Notificaciones\MensajeNotificacion;
use Illuminate\Support\Facades\Http;

/**
 * Canal de WhatsApp. La sesion de WhatsApp NO vive en PHP: corre en un
 * microservicio Node (Baileys) al que aqui se llama por HTTP interno. Asi una
 * caida/reconexion de la sesion de WhatsApp nunca tumba la API de Laravel.
 *
 * Si el microservicio responde error o esta caido, se lanza excepcion: el job
 * que invoca este canal la captura (best-effort), de modo que el correo igual
 * sale. Si el paciente no tiene telefono, el canal no hace nada.
 */
class WhatsAppNotificacionServicio implements NotificacionServicio
{
    /**
     * @param  array{url: string, token: string|null, timeout: int}  $config
     */
    public function __construct(private readonly array $config) {}

    public function enviar(MensajeNotificacion $mensaje): void
    {
        if ($mensaje->pacienteTelefono === null) {
            return;
        }

        $request = Http::timeout($this->config['timeout'])
            ->acceptJson();

        if (! empty($this->config['token'])) {
            $request = $request->withToken($this->config['token']);
        }

        $respuesta = $request->post(rtrim($this->config['url'], '/').'/mensajes', [
            'telefono' => $mensaje->pacienteTelefono,
            'tipo' => $mensaje->tipo,
            'texto' => $this->plantilla($mensaje),
        ]);

        // Un status !=2xx lanza RequestException, que el job captura como fallo
        // aislado de este canal (no afecta al correo ni a la cita).
        $respuesta->throw();
    }

    public function canal(): string
    {
        return 'whatsapp';
    }

    /**
     * Texto plano del mensaje de WhatsApp segun el tipo. Los valores ya vienen
     * sanitizados en el MensajeNotificacion.
     */
    private function plantilla(MensajeNotificacion $mensaje): string
    {
        $encabezados = [
            MensajeNotificacion::TIPO_CONFIRMACION => "Hola {$mensaje->pacienteNombre}, tu cita quedo confirmada.",
            MensajeNotificacion::TIPO_RECORDATORIO => "Hola {$mensaje->pacienteNombre}, te recordamos tu proxima cita.",
            MensajeNotificacion::TIPO_CANCELACION => "Hola {$mensaje->pacienteNombre}, tu cita fue cancelada.",
        ];

        $encabezado = $encabezados[$mensaje->tipo] ?? "Hola {$mensaje->pacienteNombre}, tienes una novedad con tu cita.";

        return implode("\n", [
            $encabezado,
            "Profesional: {$mensaje->profesionalNombre}",
            "Tratamiento: {$mensaje->tratamientoNombre}",
            "Fecha y hora: {$mensaje->fechaHora}",
            $mensaje->clinicaNombre,
        ]);
    }
}
