ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

# Extensions required by the community application: PDO MySQL for MariaDB/MySQL,
# GD (JPEG/PNG/WebP) for re-encoding uploaded images, and EXIF for orientation.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libjpeg62-turbo-dev libpng-dev libwebp-dev unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql exif \
    && apt-get purge -y --auto-remove libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && apt-get install -y --no-install-recommends libjpeg62-turbo libpng16-16 libwebp7 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/compendium.ini /usr/local/etc/php/conf.d/compendium.ini

RUN a2enmod rewrite headers \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/compendium.conf \
    && a2enconf compendium \
    && mkdir -p /var/uvs/storage \
    && chown www-data:www-data /var/uvs/storage \
    && chmod 0750 /var/uvs/storage

WORKDIR /var/www/html
