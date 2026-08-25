# Clinica Dental API

Backend (Laravel) de un SaaS multi-clinica dental: gestion de profesionales,
pacientes, tratamientos, presupuestos, sucursales, convenios y reservas, con
aislamiento por tenant. La consumen dos frontends (`clinica-dental-portal` y
`clinica-dental-paciente`) via REST; ninguna logica de negocio vive en ellos.

## Modulos

- Auth (staff y paciente, guards independientes), Usuarios, Roles y permisos
- Profesionales, Especialidades, Sucursales
- Pacientes (registro clinico + identificacion publica por RUT sin login)
- Tratamientos, Presupuestos, Convenios
- Disponibilidad y Reservas (citas)
- Notificaciones (correo + WhatsApp)
- Marca de la clinica (contenido del sitio publico de pacientes)

## Stack

- Laravel 13 (PHP 8.4)
- PostgreSQL + Redis (cache de disponibilidad y colas)
- Sanctum (auth por token) + spatie/laravel-permission (roles por tenant)
- Docker (api, worker, scheduler, db, redis, mailpit, whatsapp)

## Requisitos

- Docker Desktop

## Desarrollo local

```bash
cp .env.example .env
docker compose -f docker/docker-compose.json up -d --build
```

La API queda en `http://127.0.0.1:8081/api` (usar `127.0.0.1`, no `localhost`).

```bash
# Datos de prueba (idempotente)
docker compose -f docker/docker-compose.json exec api php artisan db:seed

# Tests
docker compose -f docker/docker-compose.json exec api php artisan test
```

## Variables de entorno

Copiar `.env.example` a `.env` y ajustar si hace falta (los defaults ya
apuntan a los servicios de Docker). Nada de secretos en el codigo.

## Documentacion de la API

Contrato OpenAPI en `resources/openapi/openapi.yaml`, fuente unica de verdad
para ambos frontends:

- UI interactiva: `http://127.0.0.1:8081/api/documentation`
- Contrato crudo: `http://127.0.0.1:8081/api/openapi.yaml`

## Notas

- A diferencia de los frontends, este proyecto si se dockeriza (7 servicios).
- Todos los mensajes de error de la API estan en espanol de Chile.
- Git: trunk-based sin pull requests — rama corta por cambio (`fix/*`,
  `feature/*`, `chore/*`), tests en verde, `merge --no-ff` a `main`.
