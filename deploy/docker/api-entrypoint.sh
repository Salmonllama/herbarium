#!/bin/sh
set -e

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public storage/app/private
chown -R www-data:www-data storage bootstrap/cache

if [ "$1" = "web" ]; then
    php artisan storage:link --force >/dev/null 2>&1 || true
    php-fpm -D
    exec nginx -g "daemon off;"
else
    exec "$@"
fi
