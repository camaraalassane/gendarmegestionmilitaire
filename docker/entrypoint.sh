#!/bin/sh
set -e

# Optimisations Laravel au dǸmarrage du conteneur en production
if [ "$APP_ENV" = "production" ] || [ "$APP_DEBUG" = "false" ]; then
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

exec "$@"
