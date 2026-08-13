# Imagen de la app Laravel para desarrollo/prueba.
# PHP 8.4 CLI + extensiones para PostgreSQL y Redis.
# Sirve la app con `php artisan serve` (suficiente para dev/test).
FROM php:8.4-cli

# Dependencias de sistema necesarias para las extensiones y utilidades.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP: PostgreSQL (pdo_pgsql/pgsql), zip, intl, bcmath, pcntl (colas).
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        zip \
        intl \
        bcmath \
        pcntl

# Extension Redis via PECL (REDIS_CLIENT=phpredis en .env).
RUN pecl install redis \
    && docker-php-ext-enable redis

# Composer (copiado desde la imagen oficial).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Entrypoint: espera la DB, instala deps si faltan, migra y levanta el server.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
