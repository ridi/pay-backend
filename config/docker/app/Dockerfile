FROM php:7.2-fpm as php-fpm

# 카카오 미러 서버로 저장소 변경
RUN sed -i -e "s/\/\/archive\.ubuntu/\/\/mirror\.kakao/" /etc/apt/sources.list

ENV LC_ALL=C.UTF-8

RUN apt-get update --fix-missing && apt-get install --no-install-recommends -y \
    libzip-dev \
    libgmp-dev

RUN pecl install apcu && docker-php-ext-enable apcu

RUN docker-php-ext-install \
    zip \
    gmp \
    pdo_mysql \
    opcache

COPY ./config/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./config/php/php.ini-production $PHP_INI_DIR/php.ini

COPY . /app
WORKDIR /app
RUN mkdir -p var && chmod -R 777 var

COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer install --optimize-autoloader
