# Clinica Dental API

Backend unico (Laravel 13 / PHP 8.4) del SaaS multi-tenant para clinicas
dentales. Una sola API sirve a dos frontends que viven en repos aparte:

- `clinica-dental-portal` — portal de gestion del staff de la clinica.
- `clinica-dental-paciente` — sitio publico + reservas de pacientes.

Ninguna logica de negocio vive en los clientes: todo pasa por esta API.

## Caracteristicas

- **Multi-tenant** por columna `tenant_id` + global scope. Nunca se recibe del
  cliente: se resuelve del usuario autenticado o del header `X-Clinica` en
  endpoints publicos.
- **Arquitectura por capas**: Controllers -> Servicios -> Repositorios -> Models.
  El controller nunca toca Eloquent directo.
- **Dos guards de auth** independientes (Sanctum con abilities): `staff` y
  `paciente`. Un token de un guard no autoriza endpoints del otro.
- **Reservas con bloqueo optimista**: constraint unico parcial
  (`professional_id` + `fecha_hora`). Si el slot se ocupo, responde 409.
- **Disponibilidad cacheada en Redis**, invalidada por evento, no por TTL.
- **Notificaciones best-effort y encoladas** (correo + WhatsApp), con
  reintentos; un fallo de notificacion nunca tumba la cita.
- **Seguridad**: CORS acotado, rate limiting, headers de seguridad + CSP, Form
  Requests en todo input, errores en espanol.

## Modulos

| Modulo | Descripcion |
|---|---|
| Auth | Guards `staff`/`paciente` independientes, roles y permisos editables por tenant |
| Profesionales | CRUD + horarios, especialidades (N:N), sucursal, foto/bio/matricula |
| Pacientes | Registro clinico (staff) + identificacion publica por RUT (sin login) |
| Tratamientos | Catalogo con ficha rica (categoria, incluye, slug), ligado a una especialidad |
| Especialidades | Catalogo por tenant; filtra el profesional elegible en la reserva |
| Sucursales | Sedes de la clinica, horario de atencion (puede variar por dia) |
| Convenios | Fonasa/isapres/cajas de compensacion que acepta la clinica |
| Presupuestos | Propuestas de tratamiento con lineas y total |
| Reservas | Citas con o sin `professional_id` (auto-asigna el primero disponible) |
| Notificaciones | Correo (Brevo/Mailpit) + WhatsApp (Baileys), colas separadas |

Contrato completo (endpoints, payloads, codigos de error) en el Swagger — ver
mas abajo. Este README no lo duplica.

## Levantar el proyecto

Requisitos: Docker Desktop.

```bash
cp .env.example .env
docker compose -f docker/docker-compose.json up -d --build
```

El entrypoint espera a Postgres, instala dependencias si falta `vendor/`,
genera `APP_KEY` y migra automaticamente. Para forzar una migracion a mano:

```bash
docker compose -f docker/docker-compose.json exec api php artisan migrate --force
```

La API queda en `http://127.0.0.1:8081/api` (usar `127.0.0.1`, no `localhost`:
en Windows con Docker Desktop/WSL2 el bind mount del codigo puede dar
respuestas lentas/intermitentes — no es un problema de DNS).
Health check: `http://127.0.0.1:8081/up`.

### Datos de prueba

```bash
docker compose -f docker/docker-compose.json exec api php artisan db:seed
```

Idempotente. Deja una clinica de prueba lista (`slug: clinica-demo`, staff
`staff@demo.cl` / `password123`) con profesionales, sucursales, convenios,
catalogo y pacientes de ejemplo.

### Comandos utiles

```bash
docker compose -f docker/docker-compose.json ps                 # estado
docker compose -f docker/docker-compose.json logs -f worker      # colas
docker compose -f docker/docker-compose.json exec api php artisan migrate:fresh --seed
docker compose -f docker/docker-compose.json down                # bajar todo
```

## Tests

```bash
docker compose -f docker/docker-compose.json exec api php artisan test
```

Corren contra SQLite en memoria (`phpunit.xml`), no contra la base de Postgres
de desarrollo. Cubren aislamiento multi-tenant, bloqueo optimista de reservas
y separacion de guards, entre otros.

## Documentacion de la API (Swagger / OpenAPI)

Fuente unica de verdad en `resources/openapi/openapi.yaml`, importable por
ambos frontends para generar clientes/tipos:

- **UI interactiva**: `http://127.0.0.1:8081/api/documentation`
- **Contrato crudo**: `http://127.0.0.1:8081/api/openapi.yaml`

## Notificaciones (correo y WhatsApp)

En desarrollo el correo se ve en Mailpit (`http://127.0.0.1:8026`, sin salir a
internet). Para produccion: `MAIL_MAILER=brevo` + credenciales SMTP en `.env`.

WhatsApp corre en un microservicio Node aparte (Baileys), en modo mock por
defecto (`WHATSAPP_MOCK=true`). Para conectar un numero real: `WHATSAPP_MOCK=false`
+ `WHATSAPP_SERVICE_TOKEN` en `.env`, reiniciar el servicio y escanear el QR
del log (`docker compose ... logs -f whatsapp`). Baileys es una libreria no
oficial (sesion de un numero normal) — para produccion con clinicas pagando
conviene migrar a la Meta Cloud API.

## Roles y permisos del staff

`spatie/laravel-permission` con "teams" mapeado a `tenant_id`: un rol de una
clinica nunca autoriza en otra. Matriz base en `App\Services\Auth\RoleProvisioner`:

- **admin**: acceso total. Rol protegido — no se puede renombrar, editarle
  los permisos ni borrarlo, y la clinica nunca puede quedarse sin ninguno.
- **profesional**: clinico completo, sin gestion de personal.
- **recepcion**: pacientes y citas, sin acceso clinico ni administrativo.

Son solo un punto de partida: `admin` puede crear roles propios y armar la
matriz de permisos a medida (`/api/staff/roles`, `/api/staff/permisos`).

## Flujo de trabajo con Git

Trunk-based, sin pull requests: toda rama sale de `main`, nombrada por tipo
(`fix/*`, `feature/*`, `chore/*`), tests en verde, `git merge --no-ff` a
`main`, se borra la rama. Conventional Commits. Sin `--force` a `main`. Sin
atribucion a IA en los commits.

## Variables de entorno

Todo por `.env` (ver `.env.example` documentado), nada de secretos en codigo:

- `DB_*`, `REDIS_*`, `MAIL_*` — infraestructura.
- `CORS_ALLOWED_ORIGINS` — dominios de los dos frontends (nunca `*`).
- `TENANT_PUBLIC_HEADER` — header del tenant publico (`X-Clinica`).
- `RATE_LIMIT_*` — limites de rate limiting.
- `NOTIFICACIONES_*`, `COLA_*`, `RECORDATORIO_*` — canales, colas, ventana.
- `WHATSAPP_*` — microservicio de WhatsApp.
- `TURNSTILE_SECRET_KEY` — verificacion humana en la identificacion publica
  por RUT (nunca compartir este valor, se configura a mano en el `.env`).

## Pendiente / fuera de alcance

- **Pago de la reserva**: fuera de alcance por decision de producto.
- **Login de paciente con cuenta/password**: la identificacion publica actual
  es solo por RUT, sin sesion; login queda para una etapa futura.
- **Tenant publico por header**: `X-Clinica` es simple para dev; en produccion
  podria resolverse por subdominio de la clinica.
