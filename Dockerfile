# Stage 1: Install dependencies
FROM composer:2 AS builder
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
COPY . .
RUN composer dump-autoload --optimize

# Stage 2: Runtime
FROM dunglas/frankenphp:1-php8.3-alpine AS runtime
WORKDIR /app

RUN install-php-extensions pdo_mysql intl opcache pcntl

COPY --from=builder /app /app
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
