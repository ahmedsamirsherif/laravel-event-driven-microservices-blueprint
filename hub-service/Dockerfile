# ─── Stage 1: base ───────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        curl \
        libpq-dev \
        libzip-dev \
        oniguruma-dev \
        linux-headers \
        autoconf \
        gcc \
        g++ \
        make \
        && docker-php-ext-install pdo pgsql pdo_pgsql zip bcmath opcache pcntl sockets \
        && pecl install redis && docker-php-ext-enable redis \
        && apk del autoconf gcc g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─── Stage 2: dev ────────────────────────────────────────────────────────────
FROM base AS dev

RUN apk add --no-cache nginx

COPY src/composer.json src/composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction

COPY src/ .
RUN composer dump-autoload --optimize

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80 8080

ENTRYPOINT ["/entrypoint.sh"]

# ─── Stage 3: prod ───────────────────────────────────────────────────────────
FROM base AS prod

RUN apk add --no-cache nginx

COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --optimize-autoloader

COPY src/ .
RUN composer dump-autoload --optimize --no-dev \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

COPY nginx.conf /etc/nginx/nginx.conf

EXPOSE 80 8080

ENTRYPOINT ["/entrypoint.sh"]
