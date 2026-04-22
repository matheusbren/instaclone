#!/bin/sh
set -e

echo "Waiting for MySQL..."
until mysqladmin ping -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" --silent 2>/dev/null; do
  sleep 2
done
echo "MySQL ready."

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi
php artisan storage:link || true

exec frankenphp run --config /etc/caddy/Caddyfile
