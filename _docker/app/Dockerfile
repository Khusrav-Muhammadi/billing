FROM php:8.1-fpm

# Install dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        apt-utils \
        libpq-dev \
        libpng-dev \
        libzip-dev \
        zip \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql bcmath gd zip sockets calendar


# Установка Node.js
RUN curl -sL https://deb.nodesource.com/setup_16.x | bash - \
    && apt-get install -y nodejs

# Copy custom php.ini file
COPY ./_docker/app/php.ini /usr/local/etc/php/conf.d/php.ini

# Install Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www
