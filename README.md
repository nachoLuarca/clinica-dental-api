# Clinica Dental API

Backend unico (Laravel 13 / PHP 8.4) del SaaS multi-tenant para clinicas
dentales. Una sola API sirve a dos frontends que viven en repos aparte y
corren en local (no se dockerizan):

- `clinica-dental-portal` — portal de gestion del staff de la clinica.
- `clinica-dental-paciente` — sitio publico + reservas de pacientes.

Ninguna logica de negocio vive en los clientes: todo pasa por esta API.

## Caracteristicas

- **Multi-tenant** por columna `tenant_id` + global scope. El `tenant_id` nunca
  se recibe del cliente: se resuelve del usuario autenticado o del header
  `X-Clinica` en endpoints publicos.
- **Arquitectura por capas**: Controllers -> Servicios -> Repositorios -> Models
  Eloquent. El controller nunca toca Eloquent directo.
- **Dos guards de auth** independientes (Sanctum con abilities): `staff` y
  `paciente`. Un token de un guard no autoriza endpoints del otro.
- **Reservas con bloqueo optimista**: constraint unico parcial
  (`professional_id` + `fecha_hora` en citas no canceladas). Si el slot se
  ocupo, responde 409 `slot_no_disponible`.
- **Disponibilidad cacheada en Redis**, invalidada por evento (crear/cancelar
  cita), no por TTL.
- **Notificaciones best-effort y encoladas** (correo + WhatsApp) en colas
  separadas de los recordatorios; un fallo de notificacion nunca tumba la cita.
- **Seguridad**: CORS acotado a los dominios de los frontends, rate limiting en
  login/registro/publicos, headers de seguridad, Form Requests en todo input.

## Stack e infraestructura (Docker)

Todo lo de contenedores vive en `docker/`. El compose levanta:

| Servicio    | Contenedor                 | Rol                                              | Puerto host |
|-------------|----------------------------|--------------------------------------------------|-------------|
| `api`       | `clinica_dental_api`       | Laravel (`php artisan serve`)                     | **8081** -> 8000 |
| `db`        | `clinica_dental_db`        | PostgreSQL 16                                    | **5433** -> 5432 |
| `redis`     | `clinica_dental_redis`     | Cache de disponibilidad + colas                 | 6379        |
| `mailpit`   | `clinica_dental_mailpit`   | Captura de correos en dev (UI web)              | **8026** -> 8025 (SMTP 1026 -> 1025) |
| `worker`    | `clinica_dental_worker`    | `queue:work --queue=notificaciones,recordatorios`| —           |
| `scheduler` | `clinica_dental_scheduler` | `schedule:work` (dispara recordatorios)         | —           |
| `whatsapp`  | `clinica_dental_whatsapp`  | Microservicio Node (Baileys) para WhatsApp      | 3001 -> 3000 |

> Los puertos del host estan remapeados (8081 / 5433 / 8026) para no chocar con
> otros proyectos que puedas tener corriendo en los puertos por defecto.

## Levantar el proyecto

Requisitos: Docker Desktop.

```bash
# 1. Copiar el .env (ajusta si hace falta; los defaults ya apuntan a los servicios)
cp .env.example .env

# 2. Construir y levantar los 7 servicios
docker compose -f docker/docker-compose.json up -d --build

# 3. (Primer arranque) el entrypoint del api espera a Postgres, corre
#    'composer install' si falta vendor/, genera APP_KEY y migra automaticamente.
#    Si necesitas forzarlo a mano:
docker compose -f docker/docker-compose.json exec api php artisan migrate --force
```

La API queda en `http://localhost:8081/api`.
Health check: `http://localhost:8081/up`.

### Datos de prueba

`php artisan db:seed` deja una clinica y staff de prueba listos para probar
el sistema sin pasos manuales (clinica `slug: clinica-demo`, staff
`staff@demo.cl` / `password123`). Es idempotente, se puede correr varias
veces sin duplicar datos.

```bash
docker compose -f docker/docker-compose.json exec api php artisan db:seed
```

### Comandos utiles

```bash
# Ver estado de los contenedores
docker compose -f docker/docker-compose.json ps

# Logs del worker de colas / scheduler
docker compose -f docker/docker-compose.json logs -f worker
docker compose -f docker/docker-compose.json logs -f scheduler

# Migraciones
docker compose -f docker/docker-compose.json exec api php artisan migrate
docker compose -f docker/docker-compose.json exec api php artisan migrate:fresh --seed

# Bajar todo
docker compose -f docker/docker-compose.json down
```

## Tests

Los tests corren dentro del contenedor `api` (usan la config de `.env.testing`):

```bash
docker compose -f docker/docker-compose.json exec api php artisan test
# o mas compacto
docker compose -f docker/docker-compose.json exec api php artisan test --compact
```

La suite cubre, entre otros, los tres puntos criticos de la arquitectura:
aislamiento multi-tenant, bloqueo optimista de reservas y separacion de los dos
guards de auth (incluyendo abilities).

## Documentacion de la API (Swagger / OpenAPI)

El contrato se mantiene a mano en `resources/openapi/openapi.yaml` (fuente unica
de verdad, importable por ambos frontends para generar clientes/tipos):

- **UI interactiva (Swagger UI)**: `http://localhost:8081/api/documentation`
- **Contrato crudo**: `http://localhost:8081/api/openapi.yaml`

Cubre todos los dominios (auth staff/paciente, profesionales+horarios,
pacientes+diagnostico, tratamientos, presupuestos, disponibilidad, citas y
endpoints publicos) con sus esquemas y codigos de error (401, 403, 404, 409
`slot_no_disponible`, 422, 429).

## Ver los correos (Mailpit)

En desarrollo el mailer apunta a Mailpit (sin salir a internet, sin auth).
Los correos que la app envie se ven en:

`http://localhost:8026`

Para produccion con Brevo: poner `MAIL_MAILER=brevo` y las credenciales SMTP de
Brevo en `.env` (ver bloque comentado en `.env.example`).

## WhatsApp real (Baileys)

El microservicio `whatsapp` (Node + Baileys) corre aparte del proceso PHP. En
dev arranca en modo mock (`WHATSAPP_MOCK=true`), asi que no requiere una sesion
real y no envia nada.

Para conectar un numero real:

1. Poner `WHATSAPP_MOCK=false` en `.env` y definir `WHATSAPP_SERVICE_TOKEN`.
2. Reiniciar el servicio: `docker compose -f docker/docker-compose.json up -d whatsapp`
3. Ver el log del contenedor para escanear el QR con WhatsApp del telefono:
   `docker compose -f docker/docker-compose.json logs -f whatsapp`
4. La sesion se persiste en el volumen `clinica_dental_whatsapp_auth`.

> Baileys es una libreria **no oficial**: usa la sesion de un numero normal.
> Aceptable para pruebas; para produccion con clinicas pagando habria que migrar
> a la Meta Cloud API. Si WhatsApp falla, el correo igual sale (best-effort).

## Estructura de capas

```
routes/api.php
  -> app/Http/Controllers (orquestan, validan via Form Requests)
    -> app/Services         (reglas de negocio)
      -> app/Repositories   (unico acceso a datos)
        -> app/Models       (Eloquent + relaciones + TenantScope)
```

## Roles y permisos del staff

`spatie/laravel-permission` con la feature "teams" mapeada a `tenant_id`
(`config/permission.php`): un rol de una clinica nunca autoriza en otra.

- **admin**: acceso total a los CRUDs del staff.
- **profesional**: ve todo lo clinico, edita pacientes/diagnosticos, no
  administra personal ni borra tratamientos/presupuestos.
- **recepcion**: alta de pacientes y citas, sin acceso a diagnosticos ni
  a la gestion de profesionales/tratamientos.

Permisos con convencion `recurso.accion` (`ver`/`crear`/`editar`/`eliminar`),
aplicados por ruta via `middlewareFor` en `routes/api.php`. El auto-registro
publico de staff (`POST /api/staff/register`) siempre asigna `recepcion`
(menor privilegio); elevar a `admin`/`profesional` se hace a mano. La matriz
completa vive en `App\Services\Auth\RoleProvisioner`, que la aprovisiona por
tenant de forma idempotente.

## Flujo de trabajo con Git

Trunk-based: todo converge a `main`, sin pull requests (estandar reconocido —
Google, Meta, research de DORA/Accelerate — no un atajo).

- **Cambios chicos**: commit directo a `main`.
- **Cambios grandes o riesgosos**: rama de vida corta (`feature/*` o `fix/*`)
  -> `git merge --no-ff` a `main` (sin PR), y se borra la rama despues.
- **No negociable**: tests en verde antes de cada commit/merge a `main` — es la
  red de seguridad que reemplaza al code review.
- Mensajes en [Conventional Commits](https://www.conventionalcommits.org/),
  chicos y atomicos.
- Sin `--force` a `main`. Sin atribucion a IA en los commits.

## Variables de entorno

Todo se configura por `.env` (ver `.env.example` documentado). Nada de secretos
en codigo. Claves principales:

- `DB_*`, `REDIS_*`, `MAIL_*` — infraestructura.
- `CORS_ALLOWED_ORIGINS` — dominios de los dos frontends (nunca `*`).
- `TENANT_PUBLIC_HEADER` — header del tenant publico (`X-Clinica`).
- `RATE_LIMIT_*` — limites de rate limiting, ajustables sin tocar codigo.
- `NOTIFICACIONES_*`, `COLA_*`, `RECORDATORIO_*` — canales, colas y ventana de
  recordatorios.
- `WHATSAPP_*`, `BREVO_*` — proveedores de notificacion.

## Pendiente / fuera de alcance

- **Pago de la reserva**: fuera de alcance por decision de producto.
- **WhatsApp**: Baileys es no oficial; migrar a Meta Cloud API para produccion.
- **Tenant publico por header**: `X-Clinica` es simple para dev; en produccion
  podria resolverse por subdominio de la clinica.
