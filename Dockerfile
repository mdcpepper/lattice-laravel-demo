ARG PHP_BASE_IMAGE=php:8.4-cli-bookworm
FROM ${PHP_BASE_IMAGE}

ARG LATTICE_EXT_REPO=mdcpepper/lattice
ARG LATTICE_EXT_TAG=latest

ENV LATTICE_EXT_REPO=${LATTICE_EXT_REPO}
ENV LATTICE_EXT_TAG=${LATTICE_EXT_TAG}
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git jq libsqlite3-dev unzip \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/install-lattice-extension.sh /usr/local/bin/install-lattice-extension
COPY docker/start-laravel.sh /usr/local/bin/start-laravel

RUN chmod +x /usr/local/bin/start-laravel

RUN /usr/local/bin/install-lattice-extension \
    && php -m | grep -Eiq '^lattice-php-ext$'

WORKDIR /app

CMD ["php", "-v"]
