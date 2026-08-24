FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev unzip curl \
    && docker-php-ext-install mbstring pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && printf '%s\n' \
      '<VirtualHost *:80>' \
      '    DocumentRoot /var/www/html/public' \
      '    <Directory /var/www/html/public>' \
      '        Options FollowSymLinks' \
      '        AllowOverride All' \
      '        Require all granted' \
      '        DirectoryIndex index.php index.html' \
      '    </Directory>' \
      '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
      '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
      '</VirtualHost>' \
      > /etc/apache2/sites-available/000-default.conf

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 80
CMD ["apache2-foreground"]
