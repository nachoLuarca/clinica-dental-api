#!/usr/bin/env bash
set -e

cd /var/www/html

# Instala dependencias si el volumen montado no las trae.
if [ ! -d vendor ]; then
  echo "[entrypoint] vendor/ no existe, ejecutando composer install..."
  composer install --no-interaction --prefer-dist
fi

# Genera APP_KEY si no hay ninguna definida.
if ! grep -q "^APP_KEY=base64:" .env 2>/dev/null; then
  echo "[entrypoint] Generando APP_KEY..."
  php artisan key:generate --force || true
fi

# Espera a que PostgreSQL acepte conexiones.
echo "[entrypoint] Esperando a PostgreSQL en ${DB_HOST:-db}:${DB_PORT:-5432}..."
until pg_isready -h "${DB_HOST:-db}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-clinica}" >/dev/null 2>&1; do
  sleep 1
done
echo "[entrypoint] PostgreSQL disponible."

# Corre migraciones (graceful: no rompe si aun no hay tablas nuevas).
php artisan migrate --force --graceful || true

# Limpia y cachea config para reflejar el .env montado.
php artisan config:clear || true

echo "[entrypoint] Levantando servidor Laravel en 0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
