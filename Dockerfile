# ── Stage 1 : build des assets JS/CSS ────────────────────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2 : image PHP de production ─────────────────────────────────────────
FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
        git zip unzip curl \
        libpng-dev libonig-dev libxml2-dev libpq-dev libzip-dev \
    && docker-php-ext-install \
        pdo pdo_pgsql pdo_mysql \
        mbstring bcmath gd zip pcntl exif \
    && a2enmod rewrite negotiation \
    && apt-get clean && rm -rf /var/lib/apt/lists/* \
    # php.ini production : display_errors=Off + output_buffering=4096
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Composer deps (sans scripts pour ne pas avoir besoin de APP_KEY au build)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Code source + assets compilés
COPY . .
COPY --from=frontend /app/public/build public/build

# Garantir l'existence des dossiers runtime (peuvent être vides après .dockerignore)
RUN mkdir -p storage/framework/{sessions,cache/data,views} \
        storage/app/public \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Apache : pointer sur public/
RUN printf '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog /proc/self/fd/2\n\
    CustomLog /proc/self/fd/1 combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

COPY deploy/render/start.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
