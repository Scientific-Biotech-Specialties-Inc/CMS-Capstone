FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    curl unzip git libpng-dev libonig-dev libxml2-dev default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-scripts --prefer-dist \
    && mkdir -p bootstrap/cache \
       storage/framework/cache/data \
       storage/framework/sessions \
       storage/framework/views \
       storage/logs \
    && chmod -R 775 bootstrap/cache storage

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
