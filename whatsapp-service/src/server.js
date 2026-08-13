import express from 'express';
import pino from 'pino';
import { WhatsAppSession } from './whatsapp.js';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });

const PORT = Number(process.env.PORT || 3000);
const AUTH_TOKEN = process.env.WHATSAPP_SERVICE_TOKEN || '';
// Modo mock: no abre una sesion real de WhatsApp, solo loguea los envios. Es el
// modo por defecto en dev/prueba, para no depender de escanear un QR con un
// numero real. En produccion se pone WHATSAPP_MOCK=false y se escanea el QR.
const MOCK = String(process.env.WHATSAPP_MOCK ?? 'true').toLowerCase() !== 'false';

const session = new WhatsAppSession({ mock: MOCK, logger });

const app = express();
app.use(express.json());

// Auth interna simple por Bearer token compartido con Laravel (WHATSAPP_SERVICE_TOKEN).
// Si no se define token, se omite (solo aceptable en dev tras red interna de Docker).
app.use((req, res, next) => {
    if (req.path === '/health') {
        return next();
    }
    if (!AUTH_TOKEN) {
        return next();
    }
    const header = req.get('authorization') || '';
    if (header !== `Bearer ${AUTH_TOKEN}`) {
        return res.status(401).json({ ok: false, error: 'no_autorizado' });
    }
    return next();
});

// Health check: reporta si la sesion esta conectada (o en modo mock).
app.get('/health', (req, res) => {
    res.json({ ok: true, mock: MOCK, estado: session.estado() });
});

// Estado detallado de la sesion (para diagnostico / mostrar QR pendiente).
app.get('/estado', (req, res) => {
    res.json({ ok: true, mock: MOCK, ...session.detalle() });
});

// Envio de un mensaje. Contrato con Laravel (WhatsAppNotificacionServicio):
//   { telefono: "+569...", tipo: "confirmacion", texto: "..." }
app.post('/mensajes', async (req, res) => {
    const { telefono, texto, tipo } = req.body || {};

    if (!telefono || !texto) {
        return res.status(422).json({ ok: false, error: 'telefono_y_texto_requeridos' });
    }

    try {
        const resultado = await session.enviar(String(telefono), String(texto), tipo);
        return res.json({ ok: true, ...resultado });
    } catch (err) {
        logger.error({ err: err.message }, 'Fallo el envio de WhatsApp');
        // 503: canal temporalmente no disponible (sesion caida, etc.). Laravel lo
        // toma como fallo aislado de este canal; el correo igual sale.
        return res.status(503).json({ ok: false, error: 'whatsapp_no_disponible' });
    }
});

session.iniciar().catch((err) => logger.error({ err: err.message }, 'No se pudo iniciar la sesion de WhatsApp'));

app.listen(PORT, () => {
    logger.info(`Microservicio de WhatsApp escuchando en :${PORT} (mock=${MOCK})`);
});
