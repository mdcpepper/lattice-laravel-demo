# Lattice Laravel Demo

This repository is a demo Laravel application for the [Lattice promotion optimisation engine](https://github.com/mdcpepper/lattice).

It includes:
- A Filament admin UI for managing products, items, carts, and promotions.
- A basic Blade + HTMX storefront for browsing categories and adding/removing cart items. Demo products are from https://dummyjson.com.

## Requirements

- Docker
- Docker Compose

## Installation

```bash
git clone https://github.com/mdcpepper/lattice-laravel-demo.git
cd lattice-laravel-demo
cp .env.example .env
```

If needed, update `HOST_UID` and `HOST_GID` in `.env` to match your local user/group IDs.

## Setup

1. Build the application image:

```bash
docker compose build app
```

2. Install PHP and JS dependencies, generate app key, run migrations, and build assets:

```bash
docker compose run --rm app composer run setup
```

3. Seed demo data:

```bash
docker compose run --rm app php artisan db:seed --force
```

`db:seed` prints credentials for the default admin user (`admin@example.com`) with a generated password.

## Running

Start the app server and queue worker:

```bash
docker compose up -d app queue
```

Open:
- Storefront: `http://localhost:8080`
- Admin panel: `http://localhost:8080/admin`

To follow logs:

```bash
docker compose logs -f app queue
```

To stop:

```bash
docker compose down
```
