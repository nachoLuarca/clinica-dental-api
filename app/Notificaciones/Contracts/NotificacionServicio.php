<?php

namespace App\Notificaciones\Contracts;

use App\Notificaciones\MensajeNotificacion;

/**
 * Interfaz comun de un CANAL de notificacion.
 *
 * Encapsula el envio detras de un contrato unico para que el resto del codigo
 * (servicios, jobs) no sepa ni le importe si detras hay correo, WhatsApp u otro
 * proveedor: solo entrega un MensajeNotificacion y el canal se encarga. Cambiar
 * de proveedor (Brevo -> otro, Baileys -> Meta Cloud API) es reemplazar la
 * implementacion sin tocar quien la usa.
 */
interface NotificacionServicio
{
    /**
     * Envia el mensaje por este canal. Debe lanzar excepcion si el envio falla;
     * el aislamiento best-effort (que un canal caido no afecte a otro ni a la
     * cita) lo garantiza el job que lo invoca, no el canal.
     */
    public function enviar(MensajeNotificacion $mensaje): void;

    /**
     * Clave del canal ('correo', 'whatsapp', ...), usada para trazas/logs.
     */
    public function canal(): string;
}
