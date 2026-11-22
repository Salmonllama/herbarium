FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    git \
    unzip \
    curl \
    wget \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        gd \
        exif \
        zip \
        intl \
        bcmath \
        opcache \
        pcntl \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY api/composer.json api/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY api/ ./
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

COPY deploy/docker/nginx.conf /etc/nginx/http.d/default.conf
COPY deploy/docker/api-entrypoint.sh /usr/local/bin/api-entrypoint.sh
RUN chmod +x /usr/local/bin/api-entrypoint.sh

RUN { \
    echo "upload_max_filesize=64M"; \
    echo "post_max_size=64M"; \
    echo "memory_limit=512M"; \
    echo "max_execution_time=120"; \
    } > /usr/local/etc/php/conf.d/herbarium.ini

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/api-entrypoint.sh"]
CMD ["web"]
