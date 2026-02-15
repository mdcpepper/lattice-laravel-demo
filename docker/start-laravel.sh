#!/usr/bin/env sh
set -eu

cd /app

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if ! grep -Eq '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --ansi
fi

mkdir -p database
touch database/database.sqlite

php artisan migrate --force --graceful --ansi

exec php -S 0.0.0.0:8080 -t /app/public /app/public/index.php
