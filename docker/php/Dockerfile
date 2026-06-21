# PHP 8.5 CLI — mirrors the production server stack (prod = PHP 8.5).
# Used for local tests/artisan so we develop on the same engine as prod.
FROM php:8.5-cli

# System libs needed to build the PHP extensions below.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libonig-dev libzip-dev libpng-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath gd zip intl \
    && rm -rf /var/lib/apt/lists/*
# openssl, tokenizer, ctype, fileinfo, curl, json, xml/dom are enabled by default in the base image.

# Composer (copied from the official composer image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dev php.ini overrides.
COPY php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html
