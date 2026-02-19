ARG PHP_BASE_IMAGE=dunglas/frankenphp:1-php8.4-bookworm
FROM ${PHP_BASE_IMAGE}

ARG LATTICE_EXT_REPO=mdcpepper/lattice
ARG LATTICE_EXT_TAG=latest
ARG HOST_UID=1000
ARG HOST_GID=1000

ENV LATTICE_EXT_REPO=${LATTICE_EXT_REPO}
ENV LATTICE_EXT_TAG=${LATTICE_EXT_TAG}
ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git jq libicu-dev libsqlite3-dev libzip-dev unzip zlib1g-dev \
    && docker-php-ext-install bcmath intl pcntl pdo_sqlite zip \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    if ! getent group "${HOST_GID}" >/dev/null; then \
    groupadd -g "${HOST_GID}" app; \
    fi; \
    if ! getent passwd "${HOST_UID}" >/dev/null; then \
    useradd -m -u "${HOST_UID}" -g "${HOST_GID}" -s /bin/bash app; \
    fi

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/install-lattice-extension.sh /usr/local/bin/install-lattice-extension

COPY docker/start-laravel.sh /usr/local/bin/start-laravel

RUN chmod +x /usr/local/bin/install-lattice-extension /usr/local/bin/start-laravel

RUN /usr/local/bin/install-lattice-extension \
    && php -m | grep -Eiq '^intl$' \
    && php -m | grep -Eiq '^pcntl$' \
    && php -m | grep -Eiq '^zip$' \
    && php -m | grep -Eiq '^lattice-php-ext$'

WORKDIR /app

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8080"]
