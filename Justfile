fmt:
    docker compose run --rm app ./vendor/bin/pint

rebuild lattice-tag='latest':
    docker compose build --pull --no-cache --build-arg LATTICE_EXT_TAG={{ lattice-tag }} app
    docker compose up -d --no-deps --force-recreate app

composer *args='':
    docker compose run --rm app composer {{ args }}

test:
    docker compose run --rm app php artisan test --parallel

artisan *args='':
    docker compose run --rm app php artisan {{ args }}

migrate:
    docker compose run --rm app php artisan migrate

rollback:
    docker compose run --rm app php artisan migrate:rollback --step=1

npm *args='':
    docker compose run --rm app npm {{ args }}
