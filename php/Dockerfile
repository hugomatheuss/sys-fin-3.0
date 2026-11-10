# syntax=docker/dockerfile:1

FROM php:8.2-fpm-bookworm

ARG APP_GID=1000
ARG APP_UID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        gosu \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pdo_pgsql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid app --create-home --shell /bin/bash app

WORKDIR /var/www/html

COPY php/docker-entrypoint.sh /usr/local/bin/sysfinan-entrypoint

RUN chmod +x /usr/local/bin/sysfinan-entrypoint

ENTRYPOINT ["sysfinan-entrypoint"]

EXPOSE 9000

CMD ["php-fpm"]
