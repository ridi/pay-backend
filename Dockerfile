FROM ubuntu:xenial

# 카카오 미러 서버로 저장소 변경
RUN sed -i -e "s/\/\/archive\.ubuntu/\/\/mirror\.kakao/" /etc/apt/sources.list

RUN apt-get update --fix-missing && apt-get install -y \
    software-properties-common \
    gettext-base \
    python3-pip \
    jq

RUN LC_ALL=C.UTF-8 apt-add-repository -y ppa:ondrej/php

RUN apt-get update --fix-missing && apt-get install -y \
    apache2 \
    php7.2 \
    php7.2-cli \
    php7.2-curl \
    php7.2-mbstring \
    php7.2-mysql \
    php7.2-apcu \
    php7.2-zip \
    php7.2-xml \
    php7.2-xdebug \
    libapache2-mod-php7.2
RUN sed -i "s/;date.timezone =/date.timezone = Asia\/Seoul/" /etc/php/7.2/apache2/php.ini && \
    sed -i "s/;date.timezone =/date.timezone = Asia\/Seoul/" /etc/php/7.2/cli/php.ini

RUN pip3 install awscli

ARG SITE

RUN a2enmod rewrite ssl
RUN a2dissite 000-default && rm /etc/apache2/sites-available/000-default.conf
COPY /config/docker/apache/${SITE}.conf /tmp/${SITE}.conf
RUN envsubst < /tmp/${SITE}.conf > /etc/apache2/sites-available/${SITE}.conf
RUN a2ensite $SITE

# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php && \
    php -r "unlink('composer-setup.php');" && \
    mv composer.phar /usr/local/bin/composer

COPY . /app
RUN mkdir -p /htdocs/app/var && chmod -R 777 /htdocs/app/var

COPY ./docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

WORKDIR /app
RUN composer install --no-dev --optimize-autoloader

ENTRYPOINT ["/docker-entrypoint.sh"]
