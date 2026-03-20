#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    bootstrap/cache \
    storage/app/private \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/events.php
rm -f bootstrap/cache/routes-*.php

chown -R www-data:www-data storage bootstrap/cache

gosu www-data php artisan storage:link --force >/dev/null 2>&1 || true

exec gosu www-data "$@"
