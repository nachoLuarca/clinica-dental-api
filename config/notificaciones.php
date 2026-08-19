<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Canales activos
    |--------------------------------------------------------------------------
    |
    | Canales por los que se emite cada notificacion de cita. El envio es
    | best-effort e INDEPENDIENTE por canal: si uno falla, el resto igual sale.
    | Se puede activar/desactivar un canal sin tocar el resto del codigo.
    |
    | Claves soportadas: 'correo', 'whatsapp'.
    |
    */

    'canales' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('NOTIFICACIONES_CANALES', 'correo,whatsapp')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Colas separadas por tipo de trabajo
    |--------------------------------------------------------------------------
    |
    | Las notificaciones inmediatas (confirmacion/cancelacion al crear/cancelar
    | una cita) y los recordatorios programados van en colas DISTINTAS, para que
    | un recordatorio masivo no atrase una confirmacion, ni viceversa.
    |
    */

    'colas' => [
        'inmediata' => env('COLA_NOTIFICACIONES', 'notificaciones'),
        'recordatorios' => env('COLA_RECORDATORIOS', 'recordatorios'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reintentos por canal (best-effort)
    |--------------------------------------------------------------------------
    |
    | Un fallo de canal (WhatsApp/correo caido de forma transitoria) no debe
    | perder la notificacion sin al menos un par de reintentos con espera.
    | Los reintentos son MANUALES, dentro del propio job (no delegados al
    | mecanismo de retry de la cola): asi el best-effort se mantiene identico
    | sin importar el driver de cola (incluido 'sync', usado en tests), donde
    | dejar que la excepcion se propague tumbaria la request.
    |
    */

    'reintentos' => [
        'intentos' => (int) env('NOTIFICACIONES_REINTENTOS', 3),
        'backoff_ms' => (int) env('NOTIFICACIONES_REINTENTOS_BACKOFF_MS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Correo
    |--------------------------------------------------------------------------
    |
    | En dev el mailer apunta a Mailpit (ver config/mail.php + .env). Para
    | produccion basta cambiar MAIL_MAILER=brevo y cargar las credenciales SMTP
    | de Brevo en .env; el codigo de la app no cambia.
    |
    */

    'correo' => [
        'mailer' => env('NOTIFICACIONES_MAILER'), // null = mailer por defecto (dev: mailpit)
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp (microservicio Node/Baileys)
    |--------------------------------------------------------------------------
    |
    | La sesion de WhatsApp NO vive en el proceso PHP: corre en un microservicio
    | Node aparte (carpeta whatsapp-service/) al que la API llama por HTTP. Si el
    | microservicio esta caido, el canal falla de forma aislada (best-effort) y
    | el correo igual se envia.
    |
    */

    'whatsapp' => [
        'url' => env('WHATSAPP_SERVICE_URL', 'http://whatsapp:3000'),
        'token' => env('WHATSAPP_SERVICE_TOKEN'),
        'timeout' => (int) env('WHATSAPP_SERVICE_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recordatorio automatico
    |--------------------------------------------------------------------------
    |
    | Cuantas horas antes de la cita se envia el recordatorio, y el ancho de la
    | ventana que barre el comando programado (para no depender de que corra al
    | segundo exacto).
    |
    */

    'recordatorio' => [
        'horas_antes' => (int) env('RECORDATORIO_HORAS_ANTES', 24),
        'ventana_minutos' => (int) env('RECORDATORIO_VENTANA_MINUTOS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | URL del sitio publico de pacientes
    |--------------------------------------------------------------------------
    |
    | Se linkea desde el correo de confirmacion de cita ("como cancelar").
    | Opcional: si no esta configurada, el correo igual explica el flujo
    | (RUT + fecha de nacimiento en "Mis horas") pero sin link clickeable.
    |
    */

    'paciente_frontend_url' => env('PACIENTE_FRONTEND_URL'),

];
