FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    curl unzip git libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan storage:link || true \
    && php artisan config:clear || true \
    && php artisan route:clear || true

EXPOSE 8080

CMD touch /data/database.sqlite && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php -S 0.0.0.0:8080 -t public
