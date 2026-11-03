FROM php:8.2-apache





RUN apt-get update -y && apt-get install -y \
    libpng-dev libxml2-dev libtool supervisor cron \
    libzip-dev zip unzip \
    libjpeg-dev libfreetype6-dev libwebp-dev \
    default-mysql-client \
    git

RUN apt-get install -y git

RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data

RUN apt-get install -y libpq-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j "$(nproc)" mysqli opcache pdo pdo_mysql gd zip soap ftp




#enable gd extension


RUN a2enmod rewrite
RUN service apache2 restart

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
RUN apt-get install -y nodejs

RUN set -ex; \
  { \
    echo "; Cloud Run enforces memory & timeouts"; \
    echo "memory_limit = -1"; \
    echo "max_execution_time = 0"; \
    echo "; File upload at Cloud Run network limit"; \
    echo "upload_max_filesize = 32M"; \
    echo "post_max_size = 32M"; \
    echo "; Configure Opcache for Containers"; \
    echo "opcache.enable = Off"; \
    echo "opcache.validate_timestamps = Off"; \
    echo "; Configure Opcache Memory (Application-specific)"; \
    echo "opcache.memory_consumption = 32"; \
  } > "$PHP_INI_DIR/conf.d/cloud-run.ini"

# Copy in custom code from the host machine.
WORKDIR /var/www/html
#COPY ./src ./


#SET APACHE DOCUMENT ROOT
RUN sed -i 's/\/var\/www\/html/\/var\/www\/html\/public/g' /etc/apache2/sites-available/000-default.conf
#SET storage permissions
#RUN chmod -R 777 storage
#RUN chown -R www-data:www-data storage
#RUN chmod -R 777 bootstrap/cache

# Use the PORT environment variable in Apache configuration files.
# https://cloud.google.com/run/docs/reference/container-contract#port
RUN sed -i 's/80/80/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Configure PHP for development.
# Switch to the production php.ini for production operations.
# RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
# https://github.com/docker-library/docs/blob/master/php/README.md#configuration
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Install and configure Xdebug for development.
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN set -eux; \
  { \
    echo "xdebug.mode=debug,develop"; \
    echo "xdebug.start_with_request=yes"; \
    echo "xdebug.client_host=127.0.0.1"; \
    echo "xdebug.client_port=9003"; \
    echo "xdebug.log_level=0"; \
  } > "$PHP_INI_DIR/conf.d/xdebug.ini"

# Copy the entrypoint script into the container.
COPY ./docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

CMD ["apache2-foreground"]
EXPOSE 80
