FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip

RUN docker-php-ext-install pdo_mysql zip

WORKDIR /var/www/html

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer