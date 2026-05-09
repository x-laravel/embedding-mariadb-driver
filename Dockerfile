ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm

# Install system dependencies and PHP MySQL extension (compatible with MariaDB)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

WORKDIR /app
