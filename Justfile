fmt:
    docker compose run --rm app ./vendor/bin/pint

test:
    docker compose run --rm app php artisan test --parallel

artisan *args='':
    docker compose run --rm app php artisan {{ args }}

migrate:
    docker compose run --rm app php artisan migrate

rollback:
    docker compose run --rm app php artisan migrate:rollback --step=1
