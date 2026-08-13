# Microservicio de WhatsApp (Baileys)

Servicio Node independiente que envia mensajes de WhatsApp por encargo de la API
de Laravel. La sesion de WhatsApp vive **aqui**, nunca dentro del proceso PHP: si
la sesion se cae o se reconecta, la API principal no se ve afectada.

## Por que separado

- Baileys mantiene un socket persistente con WhatsApp Web; eso no encaja en el
  modelo request/response de PHP.
- Aisla la reconexion: una caida de la sesion no tumba la API.
- Permite migrar el canal (a Meta Cloud API) cambiando solo este servicio.

## Contrato HTTP

La API de Laravel (`WhatsAppNotificacionServicio`) llama:

```
POST /mensajes
Authorization: Bearer <WHATSAPP_SERVICE_TOKEN>   (si esta configurado)
{ "telefono": "+569...", "tipo": "confirmacion", "texto": "..." }
```

- `200` -> mensaje aceptado/enviado.
- `503` -> canal no disponible (sesion caida). Laravel lo trata como fallo
  aislado del canal; el correo igual sale (best-effort).

Otros endpoints: `GET /health` (sin auth) y `GET /estado` (diagnostico + si hay
un QR pendiente de escanear).

## Modo mock (dev/prueba, por defecto)

Con `WHATSAPP_MOCK=true` no se abre ninguna sesion real: los envios solo se
loguean y `/mensajes` responde `ok`. Asi se prueba el flujo end-to-end sin un
numero real ni escanear un QR.

## Conectar un numero real (produccion)

1. `WHATSAPP_MOCK=false` en el `.env` del servicio.
2. Levantar el servicio y mirar los logs: Baileys imprime un **QR** en la
   terminal (o `GET /estado` indica `qr_pendiente: true`).
3. Escanear el QR desde WhatsApp del numero de la clinica
   (Ajustes -> Dispositivos vinculados).
4. Las credenciales quedan en `WHATSAPP_AUTH_DIR` (volumen persistente en Docker),
   asi no hay que re-escanear en cada reinicio. La reconexion automatica ya esta
   manejada, salvo logout explicito.

> Baileys es una libreria **no oficial** (usa la sesion de un WhatsApp normal).
> Aceptable para pruebas; para produccion real con clinicas pagando conviene
> migrar a la Meta Cloud API (cambiando solo `enviar()` en `src/whatsapp.js`).

## Local sin Docker

```
cp .env.example .env
npm install
npm start
```

## Docker

Se levanta junto al resto del backend desde `docker/docker-compose.json`
(servicio `whatsapp`).
