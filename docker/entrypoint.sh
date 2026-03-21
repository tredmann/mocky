#!/bin/sh
set -e

# Copy .env if not present
if [ ! -f /app/.env ]; then
    cp /app/.env.example /app/.env
fi

# Restore persisted APP_KEY from the storage volume so it survives container recreations.
# Without this, a new container generates a new key, invalidating all browser session cookies
# and causing CSRF token mismatches ("page expired") for users who don't reload.
PERSISTED_KEY_FILE=/app/storage/app-key.txt
if [ -f "$PERSISTED_KEY_FILE" ] && [ -z "$(grep '^APP_KEY=.\+' /app/.env)" ]; then
    PERSISTED_KEY="$(cat "$PERSISTED_KEY_FILE")"
    sed -i "s|^APP_KEY=.*|APP_KEY=$PERSISTED_KEY|" /app/.env
fi

# Generate app key if not set
if [ -z "$(grep '^APP_KEY=.\+' /app/.env)" ]; then
    php artisan key:generate --force
fi

# Persist the APP_KEY into the storage volume for next startup
grep '^APP_KEY=.\+' /app/.env | cut -d '=' -f2- > "$PERSISTED_KEY_FILE"

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
