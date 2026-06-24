# Stage 1: install dependencies
FROM composer:2 AS builder
WORKDIR /app
COPY . .
RUN composer install --no-interaction --optimize-autoloader --no-dev --ignore-platform-reqs

# Stage 2: runtime
FROM yomaokeremeta/nvh-php-base:8.4

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf

COPY --from=builder /app /app

RUN mkdir -p /tmp/client_body /tmp/proxy /tmp/fastcgi \
       /var/lib/nginx/logs /var/lib/nginx/tmp \
    && chown -R www-data:www-data \
       /app/storage /app/bootstrap/cache \
       /tmp/client_body /tmp/proxy /tmp/fastcgi \
       /var/lib/nginx \
       /var/log/nginx \
       /var/run

WORKDIR /app

EXPOSE 8000

CMD ["sh", "-c", "php artisan migrate --force && supervisord -c /etc/supervisord.conf"]
