FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers expires

WORKDIR /var/www/html
COPY . /var/www/html/
COPY php-production.ini /usr/local/etc/php/conf.d/zz-production.ini

# Keep runtime uploads outside the application image.
RUN mkdir -p /var/www/html/uploads/profiles \
    && chown -R www-data:www-data /var/www/html/uploads

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000
CMD ["/usr/local/bin/docker-entrypoint.sh"]
