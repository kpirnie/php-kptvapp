FROM php:8.4-fpm-alpine

# Install nginx, MySQL, cron, and required extensions
RUN apk add --no-cache \
    nginx \
    mysql \
    mysql-client \
    dcron \
    && docker-php-ext-install pdo_mysql mysqli opcache

# Copy nginx configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Copy application
COPY html/ /var/www/html/

# MySQL data directory
RUN mkdir -p /run/mysqld /var/lib/mysql \
    && chown -R mysql:mysql /run/mysqld /var/lib/mysql

# Startup script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

VOLUME ["/var/lib/mysql"]

ENTRYPOINT ["/entrypoint.sh"]