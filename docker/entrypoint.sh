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

# Bootstrap the first admin account if the users table is empty.
# Credentials come from env vars so they are never hardcoded.
# This is a one-time bootstrap; subsequent deploys leave existing users alone.
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1)
if [ "${USER_COUNT:-0}" = "0" ]; then
    ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
    ADMIN_PASSWORD="${ADMIN_PASSWORD:-ChangeMe123!}"
    php artisan tinker --execute="
        \$u = new \App\Models\User();
        \$u->name = 'Admin';
        \$u->email = '${ADMIN_EMAIL}';
        \$u->password = bcrypt('${ADMIN_PASSWORD}');
        \$u->role = 'admin';
        \$u->is_active = true;
        \$u->save();
        echo 'Created admin: ${ADMIN_EMAIL}';
    "
fi

# Cache config/routes/views for production performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage link (public disk)
php artisan storage:link 2>/dev/null || true

exec "$@"