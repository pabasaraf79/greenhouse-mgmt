#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

attempt=1
until php artisan migrate --force --no-interaction; do
    if [ "$attempt" -ge 10 ]; then
        echo "Database did not become ready in time, giving up." >&2
        exit 1
    fi
    echo "Database not ready yet, retrying migration ($attempt/10)..."
    attempt=$((attempt + 1))
    sleep 3
done

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
