###############################################################################
# Stage 1: Node — build frontend assets
###############################################################################
FROM node:18-alpine AS node-builder

WORKDIR /app

COPY app/package.json app/package-lock.json* ./
RUN npm i --prefer-offline

COPY app/webpack.config.js ./
COPY app/assets/ ./assets/

RUN npm run dev

###############################################################################
# Stage 2: Composer — install PHP dependencies
###############################################################################
FROM composer:2 AS composer-builder

ENV APP_ENV=dev

WORKDIR /app

COPY app/composer.json app/composer.lock* ./
RUN composer update
RUN composer install \
    --no-scripts \
    --no-interaction \
    --prefer-dist

COPY app/ .
RUN composer dump-autoload \
    && composer run-script post-install-cmd --no-interaction || true

###############################################################################
# Stage 3: PHP-FPM + Nginx (production image)
###############################################################################
FROM php:8.4-fpm-alpine AS runtime

ENV APP_ENV=dev

# Install system deps + nginx + supervisord
RUN apk add --no-cache \
        nginx \
        supervisor \
        icu-libs \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        intl \
        opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

# PHP configuration
COPY .docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Nginx configuration
COPY .docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Supervisord configuration
COPY .docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy PHP application + vendor from composer stage
COPY --from=composer-builder /app/ ./

# Copy built frontend assets from node stage
COPY --from=node-builder /app/public/build/ ./public/build/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html/var 2>/dev/null || true \
    && mkdir -p /var/www/html/var/cache /var/www/html/var/log \
    && chown -R www-data:www-data /var/www/html/var \
    && mkdir -p /run/nginx

EXPOSE 80

# Run migrations and start supervisord
COPY .docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
