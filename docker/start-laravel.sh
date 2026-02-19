#!/usr/bin/env sh
set -eu

cd /app

mkdir -p storage/logs bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache 2>/dev/null || true

# Recover from stale root-owned log files on bind mounts.
if [ -e storage/logs/laravel.log ] && [ ! -w storage/logs/laravel.log ]; then
    rm -f storage/logs/laravel.log 2>/dev/null || true
fi

if ! touch storage/logs/laravel.log 2>/dev/null; then
    export LOG_CHANNEL=stderr
    echo "warning: storage/logs/laravel.log is not writable; falling back to stderr logging" >&2
fi

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ "${LATTICE_KEEP_VITE_HOT_FILE:-0}" != "1" ] && [ -f public/hot ]; then
    if ! curl -fsS --max-time 1 http://node:5173 >/dev/null 2>&1; then
        rm -f public/hot
    fi
fi

if ! grep -Eq '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --ansi
fi

db_connection="${DB_CONNECTION:-}"
if [ -z "${db_connection}" ] && [ -f .env ]; then
    db_connection="$(grep -E '^DB_CONNECTION=' .env | tail -n 1 | cut -d '=' -f 2- | tr -d '"' | tr -d "'")"
fi

if [ "${db_connection}" = "sqlite" ]; then
    db_database="${DB_DATABASE:-}"
    if [ -z "${db_database}" ] && [ -f .env ]; then
        db_database="$(grep -E '^DB_DATABASE=' .env | tail -n 1 | cut -d '=' -f 2- | tr -d '"' | tr -d "'")"
    fi

    sqlite_path="${db_database:-database/database.sqlite}"

    if [ "${sqlite_path}" != ":memory:" ] && [ ! -f "${sqlite_path}" ]; then
        mkdir -p "$(dirname "${sqlite_path}")"
        touch "${sqlite_path}"
    fi
fi

if [ -d vendor/filament ] && [ ! -f public/css/filament/filament/app.css ]; then
    php artisan filament:assets --ansi
fi

php artisan migrate --force --graceful --ansi

exec php artisan octane:frankenphp --host=0.0.0.0 --port=8080
