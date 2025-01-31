FROM php:8.4-apache

RUN apt update && apt install -y sqlite3 libsqlite3-dev && docker-php-ext-install pdo_sqlite sqlite3

COPY . /var/www/html

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]