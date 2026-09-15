# =====================================================================
#  CertiCheck — Imagen de producción del backend Laravel.
#
#  Multi-stage:
#    vendor  → instala dependencias PHP de producción con Composer.
#    assets  → compila CSS/JS con Node + Vite (public/build).
#    runtime → PHP-FPM 8.5 (Alpine) + nginx + supervisord + scheduler.
#
#  Coolify puede construir y desplegar directamente desde este Dockerfile
#  (sin depender del buildpack Nixpacks). El contenedor levanta tres
#  procesos bajo supervisord: PHP-FPM, nginx y un loop del scheduler de
#  Laravel (php artisan schedule:run cada minuto).
# =====================================================================

# ---------- Stage: vendor -------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

# Solo los manifiestos de dependencias primero, para aprovechar la caché
# de capas de Docker (composer install solo se repite si cambian).
COPY composer.json composer.lock ./

# --no-scripts: se omite post-autoload-dump (package:discover) porque
# todavía no hay código de la app en este stage.
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# Resto del código fuente y autoloader optimizado con classmap authoritativo.
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction

# ---------- Stage: assets --------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .
RUN npm run build

# ---------- Stage: runtime (PHP-FPM + nginx + supervisord) -----------
FROM php:8.5-fpm-alpine AS runtime

# Paquetes de sistema: nginx (web server), supervisor (gestor de procesos)
# y las librerías para compilar la extensión zip. $PHPIZE_DEPS solo hace
# falta para COMPILAR las extensiones de PHP; se elimina al final para no
# inflar la imagen con el toolchain.
RUN apk add --no-cache \
        nginx \
        supervisor \
        libzip-dev \
        zlib-dev \
        $PHPIZE_DEPS

# Extensiones PHP extra: pdo_mysql (DB MySQL) y zip (ZipArchive para los
# certificados). Opcache ya viene compilado en la imagen php:8.5-fpm-alpine.
RUN docker-php-ext-install -j$(nproc) pdo_mysql
RUN docker-php-ext-install -j$(nproc) zip

# Limpieza: se elimina el toolchain para no inflar la imagen final.
RUN apk del --no-cache $PHPIZE_DEPS

WORKDIR /var/www/html

# Código + vendor desde el stage Composer y assets compilados desde el de Node.
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# Configuraciones de producción: nginx, supervisord, ini de PHP y pool de PHP-FPM.
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/schedule.sh /usr/local/bin/schedule.sh
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/schedule.sh /usr/local/bin/entrypoint.sh

# Directorios de runtime que Laravel necesita escribir (logs, sesiones, vistas,
# caché) y permisos para el usuario www-data con el que corre PHP-FPM.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache public; \
    chmod -R ug+rw storage bootstrap/cache

# Manifiesto de paquetes generado durante el build (evita package:discover
# en cada arranque del contenedor).
RUN php artisan package:discover --ansi || true

# Healthcheck estándar de Laravel (/up) lo usa Coolify.
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
STOPSIGNAL SIGTERM