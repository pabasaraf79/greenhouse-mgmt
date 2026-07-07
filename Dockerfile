# syntax=docker/dockerfile:1

# ---- Base: PHP 8.3 + Apache + extensions this app actually uses ----
FROM php:8.3-apache AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libxml2-dev libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring xml curl zip gd bcmath exif \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# ---- Vendor: install PHP dependencies (needs the full app for package:discover) ----
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ---- Frontend: compile Bootstrap 5 / Chart.js assets with Vite ----
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ---- Final runtime image ----
FROM base AS app
COPY --from=vendor /var/www/html ./
COPY --from=frontend /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
