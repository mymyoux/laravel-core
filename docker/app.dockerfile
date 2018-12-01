FROM php:7.3-rc-fpm

RUN apt-get update && apt-get install -y libmcrypt-dev \
    mysql-client libmagickwand-dev git libzip-dev unzip zip --no-install-recommends \
    && pecl install imagick \
    && pecl install mcrypt-1.0.1 \
    && pecl install redis \
    && docker-php-ext-enable mcrypt \
    && docker-php-ext-enable redis \
    && docker-php-ext-enable imagick \
    && docker-php-ext-install pdo_mysql zip \
    && pecl clear-cache