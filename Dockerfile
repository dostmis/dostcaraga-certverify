FROM php:8.2-fpm-bookworm AS php-base

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ghostscript \
        gosu \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        exif \
        gd \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pgsql \
        simplexml \
        xml \
        xmlreader \
        xmlwriter \
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

FROM node:20-bookworm-slim AS node-builder

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY postcss.config.js ./
COPY tailwind.config.js ./

RUN npm run build

FROM php-base AS app-runtime

COPY . .
COPY --from=composer-deps /var/www/html/vendor ./vendor
COPY --from=composer-deps /var/www/html/bootstrap/cache ./bootstrap/cache
COPY --from=node-builder /app/public/build ./public/build
COPY docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint-app

RUN chmod +x /usr/local/bin/docker-entrypoint-app \
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

FROM nginx:1.27-alpine AS nginx

WORKDIR /var/www/html

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public ./public
COPY --from=node-builder /app/public/build ./public/build

RUN rm -rf /var/www/html/public/storage \
    && mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage
