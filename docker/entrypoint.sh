#!/bin/sh
# Entrypoint del contenedor Laravel. Orden:
#   1) config:cache   → acumula la configuración leída desde ENV.
#   2) route:cache    → acumula las rutas.
#   3) storage:link   → enlaza storage/app/public → public/storage.
#   4) migrate --force → SOLO si RUN_MIGRATIONS=true (default false), para
#                         que el deploy controle explícitamente las migraciones.
#   5) supervisord    → proceso principal (php-fpm + nginx + scheduler).
set -eu

cd /var/www/html

echo "[entrypoint] Caching Laravel config..."
php artisan config:cache --no-interaction

echo "[entrypoint] Caching Laravel routes..."
php artisan route:cache --no-interaction

echo "[entrypoint] Linking public storage..."
php artisan storage:link --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "[entrypoint] RUN_MIGRATIONS=true: running migrations..."
    php artisan migrate --force --no-interaction
else
    echo "[entrypoint] RUN_MIGRATIONS unset or false: skipping migrations."
fi

echo "[entrypoint] Starting supervisord..."
exec supervisord -c /etc/supervisord.conf -n