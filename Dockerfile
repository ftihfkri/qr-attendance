FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache git curl zip unzip nginx supervisor gettext libzip-dev \
    && docker-php-ext-install pdo_mysql opcache zip

# PHP-FPM listens internally on 9001; nginx owns the public $PORT.
RUN printf "[www]\nlisten = 127.0.0.1:9001\n" > /usr/local/etc/php-fpm.d/zzz-port.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP deps first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# App source
COPY . .

RUN composer run-script post-autoload-dump || true \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
