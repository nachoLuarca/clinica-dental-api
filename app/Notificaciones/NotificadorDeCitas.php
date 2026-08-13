<?php

namespace App\Notificaciones;

use App\Jobs\EnviarNotificacionCita;
use App\Models\Appointment;

/**
 * Punto de entrada de las notificaciones de citas para el resto del dominio.
 *
 * El AppointmentService solo llama a confirmacion()/cancelacion()/recordatorio()
 * y no sabe nada de canales, colas ni plantillas: aqui se traduce la cita a un
 * MensajeNotificacion y se ENCOLA un job por cada canal activo (best-effort e
 * independiente). Asi la logica de reservas y la de notificaciones quedan
 * desacopladas en ambos sentidos.
 *
 * Colas separadas por tipo de trabajo:
 *   - confirmacion/cancelacion (inmediatas) -> cola 'notificaciones'.
 *   - recordatorio (programado)             -> cola 'recordatorios'.
 */
class NotificadorDeCitas
{
    public function __construct(private readonly CanalNotificacionManager $canales) {}

    public function confirmacion(Appointment $cita): void
    {
        $this->encolar(MensajeNotificacion::TIPO_CONFIRMACION, $cita, $this->colaInmediata());
    }

    public function cancelacion(Appointment $cita): void
    {
        $this->encolar(MensajeNotificacion::TIPO_CANCELACION, $cita, $this->colaInmediata());
    }

    public function recordatorio(Appointment $cita): void
    {
        $this->encolar(MensajeNotificacion::TIPO_RECORDATORIO, $cita, $this->colaRecordatorios());
    }

    private function encolar(string $tipo, Appointment $cita, string $cola): void
    {
        $mensaje = MensajeNotificacion::desdeCita($tipo, $cita);

        foreach ($this->canales->activos() as $canal) {
            EnviarNotificacionCita::dispatch($mensaje, $canal)->onQueue($cola);
        }
    }

    private function colaInmediata(): string
    {
        return config('notificaciones.colas.inmediata', 'notificaciones');
    }

    private function colaRecordatorios(): string
    {
        return config('notificaciones.colas.recordatorios', 'recordatorios');
    }
}
