# Install PHP dependencies
FROM composer:2 AS vendor

WORKDIR /app
COPY composer*.json ./
RUN composer install --no-dev --no-interaction --no-scripts --ignore-platform-reqs

# Build assets
FROM node:22-alpine AS assets

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# App
FROM dunglas/frankenphp:1-php8.5-alpine

RUN install-php-extensions \
    opcache \
    pcntl \
    pdo_sqlite \
    zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]
