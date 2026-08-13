# Microservicio de WhatsApp (Baileys). Corre aparte del proceso PHP.
FROM node:20-alpine

WORKDIR /app

# git: algunas dependencias de Baileys se resuelven desde repositorios git.
RUN apk add --no-cache git

# Instala dependencias primero para aprovechar la cache de capas.
COPY package.json ./
RUN npm install --omit=dev

COPY src ./src

# Directorio persistente para las credenciales de la sesion de WhatsApp.
RUN mkdir -p /app/auth
VOLUME ["/app/auth"]

EXPOSE 3000

CMD ["node", "src/server.js"]
