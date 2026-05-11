FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2enmod rewrite headers

WORKDIR /var/www/html

COPY . /var/www/html/

RUN printf '%s\n' "<?php header('Location: /backend/loginpage.php'); exit;" > /var/www/html/index.php \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

