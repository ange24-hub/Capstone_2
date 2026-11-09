#!/bin/sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY is required. Set a permanent Laravel encryption key in Dokploy Environment.' >&2
    exit 1
fi

# Named volumes can initially be empty or owned by root.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Rebuild runtime caches with runtime secrets, never with build-time credentials.
gosu www-data php artisan config:clear
gosu www-data php artisan package:discover --ansi
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache

# General migrations remain opt-in; only this release's additive forecast
# storage is prepared automatically. Never seed demo accounts on startup.
if [ "${RUN_MIGRATIONS:-false}" = 'true' ]; then
    gosu www-data php artisan migrate --force --no-interaction
else
    # These reviewed releases only add completeness confirmations. No existing
    # records are rewritten and other pending migrations remain opt-in.
    gosu www-data php artisan migration:prepare-forecast-storage --no-interaction
fi

# Optional feature storage must not prevent the existing web server from starting.
# A failed preparation still needs operator review before using the affected feature.
gosu www-data php artisan migration:prepare-statistical-storage --no-interaction \
    || echo 'Statistical forecast storage preparation failed; review app logs and rerun migration:prepare-statistical-storage.' >&2
gosu www-data php artisan population:prepare-submission-storage --no-interaction \
    || echo 'Population submission storage preparation failed; review app logs and rerun population:prepare-submission-storage.' >&2

# One-time recipient-encrypted transfer explicitly requested for this installation.
# A sync failure rolls back its transaction and must not take the existing website offline.
gosu www-data php -d memory_limit=512M artisan brbi:sync-release --no-interaction \
    || echo 'BRBI data sync needs private review; the existing website remains available.' >&2

if [ "${1:-}" = 'apache2-foreground' ]; then
    exec docker-php-entrypoint "$@"
fi

exec gosu www-data "$@"
