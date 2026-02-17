FROM docker.io/library/php:8.4-fpm-alpine

# Install all packages and build PHP extensions in one layer
RUN apk add --no-cache \
    nginx \
    dcron \
    curl \
    # Add build dependencies as virtual package
    && apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql mysqli opcache \
    # Install Redis extension
    && yes '' | pecl install redis \
    && docker-php-ext-enable redis \
    # Cleanup build dependencies
    && apk del .build-deps 

# Copy all config/app files in one layer
COPY config/nginx.conf /etc/nginx/http.d/default.conf
COPY config/php-custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY config/schema.sql /schema.sql
COPY site/ /var/www/html/
COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80
VOLUME ["/var/lib/mysql"]

ENTRYPOINT ["/entrypoint.sh"]