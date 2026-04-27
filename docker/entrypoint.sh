#!/bin/sh
set -e

until nc -z "${DB_HOST}" "${DB_PORT:-3306}" 2>/dev/null; do
  sleep 1
done

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi
php artisan storage:link || true

exec frankenphp run --config /etc/caddy/Caddyfile
