FROM php:8.4-apache

RUN apt-get update 
RUN apt-get install -y sqlite3 libsqlite3-dev

COPY . /var/www/html

WORKDIR /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]