FROM php:8.1.12-alpine
MAINTAINER fresh@idealogica.me

ENV TZ=UTC

WORKDIR /app

COPY ./docker/php/conf.d/php.ini /usr/local/etc/php/php.ini

RUN echo "http://dl-4.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories && \
    apk update && \
    apk add --no-cache libpng-dev sqlite icu-dev

RUN docker-php-ext-configure intl
RUN docker-php-ext-install mysqli pdo pdo_mysql gd intl
RUN docker-php-source delete

# Composer

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php && \
    mv composer.phar /usr/local/bin/composer && \
    composer global require doctrine/orm && \
    composer global require doctrine/migrations

# MC and telnet

RUN apk add mc && \
    apk add busybox-extras

# Shell aliases

ENV ENV="/root/.ashrc"
RUN echo "alias ll='ls -al'" > "$ENV" && \
    echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> "$ENV"
