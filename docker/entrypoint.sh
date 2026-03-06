#!/bin/sh
set -e

# Copy .env if not present
if [ ! -f /app/.env ]; then
    cp /app/.env.example /app/.env
fi

# Generate app key if not set
if [ -z "$(grep '^APP_KEY=.\+' /app/.env)" ]; then
    php artisan key:generate --force
fi

# Create SQLite database if not present
if [ "$DB_CONNECTION" = "sqlite" ] || grep -q "^DB_CONNECTION=sqlite" /app/.env; then
    DB_PATH=$(grep '^DB_DATABASE=' /app/.env | cut -d '=' -f2)
    DB_PATH="${DB_PATH:-/app/database/database.sqlite}"

    mkdir -p "$(dirname "$DB_PATH")"

    if [ ! -f "$DB_PATH" ]; then
        touch "$DB_PATH"
    fi
fi

# Run migrations
php artisan migrate --force

# Cache config/routes/views in production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
