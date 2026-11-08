FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    curl unzip git libpng-dev libonig-dev libxml2-dev libssl-dev pkg-config autoconf build-essential \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions mongodb zip

RUN echo "upload_max_filesize = 12M" > /usr/local/etc/php/conf.d/uploads-limit.ini \
    && echo "post_max_size = 15M" >> /usr/local/etc/php/conf.d/uploads-limit.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-scripts --prefer-dist

RUN mkdir -p storage/app/public && ln -s ../storage/app/public public/storage

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]