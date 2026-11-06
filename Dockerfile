# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.5-fpm-alpine AS runtime
WORKDIR /var/www/html

ARG VCS_REF=""
LABEL org.opencontainers.image.revision=$VCS_REF
LABEL org.opencontainers.image.licenses="GPL-3.0-only"

RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev sqlite-dev \
    && docker-php-ext-install intl pdo pdo_sqlite zip opcache

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN rm -f .env .env.* \
    && printf '%s' "${VCS_REF:-no-vcs-ref}" > /var/www/html/.image-revision \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache /var/lib/dsa-abenteuerfinder \
    && chown -R www-data:www-data storage bootstrap/cache /var/lib/dsa-abenteuerfinder \
    && chmod -R ug+rwx storage bootstrap/cache /var/lib/dsa-abenteuerfinder

COPY deploy/scripts/public-init.sh /usr/local/bin/dsa-public-init.sh
RUN chmod 755 /usr/local/bin/dsa-public-init.sh

USER www-data
EXPOSE 9000
CMD ["php-fpm"]
