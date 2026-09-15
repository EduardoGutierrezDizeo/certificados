#!/bin/sh
# Loop del scheduler de Laravel, supervisado por supervisord.
# Ejecuta las tareas programadas (p. ej. subscriptions:notify-expiring a las
# 08:00) una vez por minuto. Es idempotente: Laravel corre cada tarea como
# máximo una vez por minuto/día.
set -e

cd /var/www/html

while true; do
    /usr/local/bin/php artisan schedule:run --no-interaction
    sleep 60
done