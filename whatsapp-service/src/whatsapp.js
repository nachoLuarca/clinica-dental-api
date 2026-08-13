import { Boom } from '@hapi/boom';

/**
 * Encapsula la sesion de WhatsApp (Baileys) con manejo de reconexion.
 *
 * En modo mock no abre ninguna sesion real: solo loguea los envios y responde
 * ok. Es el modo por defecto en dev/prueba, para no depender de un numero real.
 *
 * En modo real, usa Baileys: guarda las credenciales en disco (multi-file auth),
 * imprime el QR para vincular el numero la primera vez, y se reconecta solo si
 * la sesion se cae (salvo que el motivo sea logout). Toda esta logica vive AQUI,
 * en el proceso Node, nunca en PHP: si WhatsApp se desconecta, la API de Laravel
 * ni se entera.
 */
export class WhatsAppSession {
    constructor({ mock, logger }) {
        this.mock = mock;
        this.logger = logger;
        this.sock = null;
        this.conectado = false;
        this.ultimoQr = null;
    }

    async iniciar() {
        if (this.mock) {
            this.logger.info('WhatsApp en modo MOCK: los envios solo se loguean.');
            return;
        }
        await this.conectar();
    }

    async conectar() {
        // Import dinamico: en modo mock ni se carga Baileys (util para tests/CI).
        const baileys = await import('@whiskeysockets/baileys');
        const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion } = baileys;
        const qrcode = (await import('qrcode-terminal')).default;

        const authDir = process.env.WHATSAPP_AUTH_DIR || './auth';
        const { state, saveCreds } = await useMultiFileAuthState(authDir);
        const { version } = await fetchLatestBaileysVersion();

        this.sock = makeWASocket({ version, auth: state, printQRInTerminal: false });

        this.sock.ev.on('creds.update', saveCreds);

        this.sock.ev.on('connection.update', (update) => {
            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                this.ultimoQr = qr;
                this.logger.info('Escanea este QR con WhatsApp para vincular el numero:');
                qrcode.generate(qr, { small: true });
            }

            if (connection === 'open') {
                this.conectado = true;
                this.ultimoQr = null;
                this.logger.info('Sesion de WhatsApp conectada.');
            }

            if (connection === 'close') {
                this.conectado = false;
                const motivo = new Boom(lastDisconnect?.error)?.output?.statusCode;
                const debeReconectar = motivo !== DisconnectReason.loggedOut;
                this.logger.warn({ motivo }, `Sesion cerrada. Reconectar: ${debeReconectar}`);
                if (debeReconectar) {
                    setTimeout(() => this.conectar().catch((e) => this.logger.error({ err: e.message }, 'Fallo reconexion')), 3000);
                }
            }
        });
    }

    async enviar(telefono, texto, tipo) {
        if (this.mock) {
            this.logger.info({ telefono, tipo }, 'MOCK WhatsApp -> ' + texto.replace(/\n/g, ' | '));
            return { mock: true, entregado: false };
        }

        if (!this.conectado || !this.sock) {
            throw new Error('Sesion de WhatsApp no conectada');
        }

        const jid = this.aJid(telefono);
        const res = await this.sock.sendMessage(jid, { text: texto });
        return { entregado: true, id: res?.key?.id ?? null };
    }

    aJid(telefono) {
        const soloDigitos = String(telefono).replace(/[^\d]/g, '');
        return `${soloDigitos}@s.whatsapp.net`;
    }

    estado() {
        if (this.mock) return 'mock';
        return this.conectado ? 'conectado' : 'desconectado';
    }

    detalle() {
        return {
            estado: this.estado(),
            qr_pendiente: this.ultimoQr !== null,
        };
    }
}
