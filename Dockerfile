# SmartPOS API image. Render builds this straight from the GitHub repo on every push.

# ---- 1. Composer dependencies --------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-req=ext-pdo_pgsql
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---- 2. Runtime: PHP 8.4 (current patch release) + Apache -----------------
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-smartpos.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Render injects $PORT; Apache reads it via ${PORT} in the config.
ENV PORT=10000
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
    && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html

EXPOSE 10000

# Apply pending migrations, seed demo data on an empty DB, then serve.
CMD ["sh", "-c", "php bin/console migrate && php bin/console seed --if-empty && exec apache2-foreground"]
