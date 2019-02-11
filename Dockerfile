FROM ubuntu:bionic

ARG DEBIAN_FRONTEND=noninteractive

# 카카오 미러 서버로 저장소 변경
RUN sed -i -e "s/\/\/archive\.ubuntu/\/\/mirror\.kakao/" /etc/apt/sources.list

ENV LC_ALL=C.UTF-8

RUN apt-get update --fix-missing && apt-get install --no-install-recommends -y \
    software-properties-common \
    wget \
    php7.2 \
    php7.2-cli \
    php7.2-curl \
    php7.2-mbstring \
    php7.2-mysql \
    php7.2-apcu \
    php7.2-zip \
    php7.2-xml \
    php7.2-xdebug \
    apache2 \
    libapache2-mod-php7.2
RUN sed -i "s/;date.timezone =/date.timezone = Asia\/Seoul/" /etc/php/7.2/apache2/php.ini && \
    sed -i "s/;date.timezone =/date.timezone = Asia\/Seoul/" /etc/php/7.2/cli/php.ini

RUN a2enmod rewrite
RUN a2dissite 000-default && rm /etc/apache2/sites-available/000-default.conf
COPY config/apache/override.conf /etc/apache2/conf-available/override.conf
RUN a2enconf override
COPY config/apache/ridi-pay.conf /etc/apache2/sites-available/ridi-pay.conf
RUN a2ensite ridi-pay

COPY . /app

# Install composer
RUN sh /app/bin/composer_installation.sh && mv composer.phar /usr/local/bin/composer

RUN mkdir -p /app/var && chmod -R 777 /app/var

WORKDIR /app
RUN composer install --no-dev --optimize-autoloader

CMD apachectl -D FOREGROUND