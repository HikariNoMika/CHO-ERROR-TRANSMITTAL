#!/usr/bin/env bash
set -e

cd /var/www/html

# Ensure the sqlite database file exists when using sqlite
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    mkdir -p database
    touch database/database.sqlite
    chown www-data:www-data database/database.sqlite
fi

# Ensure a .env file exists (Laravel's key:generate needs one to write to)
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        touch .env
    fi
fi

# Generate app key if missing
if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "" ]; then
    php artisan key:generate --force
fi

# Run migrations (idempotent)
php artisan migrate --force

# Cache config/routes/views for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage link (public disk)
php artisan storage:link 2>/dev/null || true

exec "$@"