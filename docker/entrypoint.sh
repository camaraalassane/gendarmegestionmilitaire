#!/bin/sh
set -e

# Optimisations Laravel au démarrage du conteneur en production
if [ "$APP_ENV" = "production" ] || [ "$APP_DEBUG" = "false" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

# Permissions pour www-data sur storage et bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

exec "$@"
