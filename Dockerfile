FROM php:8.3-fpm-bookworm AS php-base

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
        gosu \
        git \
        imagemagick \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmagickwand-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
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

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-cert-verify.ini

FROM php-base AS composer-deps

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=

COPY . .

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php-base AS app-runtime

COPY . .
COPY --from=composer-deps /var/www/html/vendor ./vendor
COPY --from=composer-deps /var/www/html/bootstrap/cache ./bootstrap/cache
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint-app

RUN chmod +x /usr/local/bin/docker-entrypoint-app \
    && if [ ! -f public/build/manifest.json ]; then \
        echo "Missing Vite build output. Run 'npm ci && npm run build' on the host before docker compose build." >&2; \
        exit 1; \
    fi \
    && mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["docker-entrypoint-app"]
CMD ["php-fpm"]

FROM php-base AS nginx

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
        nginx \
        wget \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public ./public

RUN rm -f /etc/nginx/sites-enabled/default \
    && if [ ! -f public/build/manifest.json ]; then \
        echo "Missing Vite build output. Run 'npm ci && npm run build' on the host before docker compose build." >&2; \
        exit 1; \
    fi \
    && rm -rf /var/www/html/public/storage \
    && mkdir -p /var/www/html/storage/app/public \
    && mkdir -p /run/nginx \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
