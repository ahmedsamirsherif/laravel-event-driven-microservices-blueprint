#!/bin/sh
set -e

# Create .env file if missing (needed by Laravel key:generate)
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Generate app key if placeholder
if echo "$APP_KEY" | grep -q "placeholder"; then
    NEW_KEY=$(php artisan key:generate --show 2>/dev/null)
    sed -i "s|^APP_KEY=.*|APP_KEY=${NEW_KEY}|" /var/www/html/.env
    export APP_KEY="${NEW_KEY}"
fi

# Run migrations
php artisan migrate --force --no-interaction

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start PHP-FPM in background
php-fpm -D

# Start Nginx in foreground
exec nginx -g "daemon off;"
