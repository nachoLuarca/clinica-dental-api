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

> **Importante**: `phpunit.xml` fuerza (`force="true"`) sus variables de
> entorno (sqlite en memoria, etc.). Sin eso, como Docker ya define
> `DB_CONNECTION=pgsql` a nivel de proceso via `env_file`, dotenv/PHPUnit no
> pisan ese valor y los tests corren contra la base real -con `RefreshDatabase`
> borrandola-. Si algun dia la demo aparece vacia sin razon aparente despues
> de correr tests, revisar primero que ningun `<env>` de `phpunit.xml` haya
> perdido su `force="true"`.

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
publico de staff (`POST /api/staff/register`) **no asigna ningun rol**: como
los roles son editables (ver abajo), no hay ninguno "seguro" que asumir de
antemano (hasta `recepcion` se puede renombrar o borrar). Un admin de la
clinica asigna el rol despues via `PATCH /api/staff/users/{id}/rol`. La
matriz base vive en `App\Services\Auth\RoleProvisioner`, que la aprovisiona
por tenant de forma idempotente (solo la primera vez que se crea cada rol;
`admin` se resincroniza siempre porque esta protegido y nadie lo edita a mano).

### Gestion de usuarios, roles y permisos (`admin` unicamente)

Los 3 roles de base son solo un punto de partida: `admin` puede crear sus
propios roles y armar la matriz de permisos a medida, similar a `col_api`.

- `GET/POST /api/staff/roles`, `GET/PUT/DELETE /api/staff/roles/{id}`,
  `PATCH /api/staff/roles/{id}/permisos` — CRUD de roles y su matriz.
- `GET /api/staff/permisos` — catalogo de permisos agrupado por recurso
  (alimenta el formulario de creacion/edicion de roles del frontend).
- `GET/POST /api/staff/users`, `GET/PUT /api/staff/users/{id}`,
  `PATCH /api/staff/users/{id}/rol`, `PATCH /api/staff/users/{id}/estado`,
  `PATCH /api/staff/users/{id}/password` — alta, edicion, cambio de rol,
  activar/desactivar (soft, columna `activo`; un staff desactivado no puede
  loguear y pierde sus sesiones) y reseteo de password del staff.

**Salvaguardas** (`App\Exceptions\RolProtegidoException` / `UltimoAdminException`
/ `OperacionSobreSiMismoException`):

- El rol `admin` (`RoleProvisioner::ROL_PROTEGIDO`) nunca se puede renombrar,
  editarle los permisos ni borrarlo.
- Nunca se puede dejar la clinica sin ningun staff activo capaz de gestionar
  roles/usuarios (permiso `roles.editar`, `RoleProvisioner::PERMISO_GESTION`):
  ni desactivando al ultimo admin, ni quitandole ese permiso al ultimo rol
  que lo tiene.
- Un staff no puede desactivarse a si mismo ni quitarse a si mismo el rol/
  permiso de gestion (evita bloquearse por accidente).
- Un rol no se puede borrar si todavia tiene staff asignado (hay que
  reasignarlos primero).

## Marca de la clinica

`GET/PATCH /api/staff/tenant` — nombre, logo y color de la propia clinica.
Sin `{tenant}` en la ruta: siempre es la del usuario autenticado, nunca un id
que mande el cliente. `ver` lo tienen los 3 roles (la UI necesita mostrarlo
siempre); `editar` es exclusivo de `admin`.

El logo se sube al disco `public` (`storage/app/public/logos`, servido via
`APP_URL/storage/...` gracias al symlink que crea el entrypoint de Docker) y
la respuesta incluye `logo_url` ya resuelta. Como Laravel no parsea `PATCH`
con body `multipart/form-data`, la subida de logo requiere method spoofing:
`POST` con campo `_method=PATCH`.

`GET /api/publico/tenant` expone la misma marca (`nombre`, `logo_url`,
`color_primario`, sin `slug` ni `activo`) para el sitio publico de pacientes,
sin login: tenant resuelto por `X-Clinica` igual que el resto de `/publico`.

## Sitio publico de pacientes: gestion de citas sin login (RUT)

Los pacientes tienen una columna `rut` (unica por tenant, normalizada al
guardar -sin puntos, digito verificador en mayuscula- via un mutator en el
modelo `Patient`, asi que da lo mismo si se escribe `12.345.678-9` o
`12345678-9`). Es **requerida** en el auto-registro (`POST /api/paciente/register`)
y opcional en el alta que hace el staff.

Con eso, el sitio publico puede listar y cancelar las citas de un paciente
**sin login**:

- `GET /api/publico/citas` — lista paginada, filtra por `rut` + `fecha_nacimiento`.
- `DELETE /api/publico/citas/{id}` — cancela, mismos `rut` + `fecha_nacimiento`
  (como query params o body JSON).

Igual que el resto de `/publico`, el tenant sale del header `X-Clinica` y las
rutas tienen `throttle:publico` (rate limit por tenant + IP). El RUT solo es
un dato bastante adivinable/conocido, por eso el lookup exige **RUT + fecha
de nacimiento juntos** (`App\Services\Publico\PatientLookupService`) — un RUT
correcto con la fecha equivocada da el mismo error generico que un RUT
inexistente, para no habilitar enumeracion de pacientes.

`GET /api/publico/profesionales` lista los profesionales activos (id, nombre,
apellido, especialidad -nunca el email interno-) para que el sitio publico
pueda mostrarlos o confirmar que existen antes de reservar.

### Modo "cualquier profesional disponible"

`professional_id` es **opcional** tanto en `GET /*/availability` como al crear
una cita (`POST /*/appointments`), en los tres frontends (publico, staff y
paciente comparten el mismo `AvailabilityRequest`/`AppointmentService`):

- **Disponibilidad sin `professional_id`**: agrega los slots libres de todos
  los profesionales activos del tenant. Cada slot trae su propio
  `professional_id` (si dos profesionales tienen el mismo horario libre,
  aparecen dos entradas), para que el frontend pueda mostrar quien lo cubre.
- **Reserva sin `professional_id`**: el servicio prueba, en orden, cada
  profesional activo cuyo horario cubra el slot pedido; si el primer
  candidato pierde la carrera contra otra reserva concurrente, sigue con el
  siguiente en vez de fallar de una. Si ninguno tiene el horario libre,
  responde 409 igual que el modo con profesional fijo.

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
