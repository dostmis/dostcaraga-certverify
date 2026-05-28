FROM php:8.3-cli-bookworm AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN set -eux; \
    if [ -f /etc/apt/sources.list.d/debian.sources ]; then \
        sed -i \
            -e 's|http://deb.debian.org/debian|https://deb.debian.org/debian|g' \
            -e 's|http://deb.debian.org/debian-security|https://deb.debian.org/debian-security|g' \
            -e 's|http://security.debian.org/debian-security|https://security.debian.org/debian-security|g' \
            /etc/apt/sources.list.d/debian.sources; \
    fi; \
    if [ -f /etc/apt/sources.list ]; then \
        sed -i \
            -e 's|http://deb.debian.org/debian|https://deb.debian.org/debian|g' \
            -e 's|http://deb.debian.org/debian-security|https://deb.debian.org/debian-security|g' \
            -e 's|http://security.debian.org/debian-security|https://security.debian.org/debian-security|g' \
            /etc/apt/sources.list; \
    fi; \
    apt-get update -o Acquire::Retries=3 -o Acquire::http::Timeout=30 -o Acquire::https::Timeout=30 \
    && apt-get install -y --no-install-recommends \
        ghostscript \
        git \
        imagemagick \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmagickwand-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        nodejs \
        npm \
        unzip \
        zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Development php.ini settings
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/99-dev.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/99-dev.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/99-dev.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/99-dev.ini

FROM php-base AS app-runtime

# Install dependencies (dev mode includes dev packages)
RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader 2>/dev/null || true

FROM php-base AS vite-dev

# Node.js is already installed in php-base