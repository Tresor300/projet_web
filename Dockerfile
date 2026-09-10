FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo_mysql \
    && apt-get purge -y --auto-remove linux-libc-dev \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
    