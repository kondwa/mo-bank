FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-configure pdo_sqlite sqlite3 \
    && docker-php-ext-install pdo_sqlite sqlite3

RUN a2enmod rewrite

COPY . /var/www/html

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]