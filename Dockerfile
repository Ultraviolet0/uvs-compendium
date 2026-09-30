ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

RUN a2enmod rewrite \
    && printf 'ServerName localhost\n<Directory /var/www/html>\n    AllowOverride All\n</Directory>\n' > /etc/apache2/conf-available/compendium.conf \
    && a2enconf compendium

WORKDIR /var/www/html
