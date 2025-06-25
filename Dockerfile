FROM php:8.2-apache

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install required tools (git, unzip) and Composer
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

# Copy app code to Apache root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies (if composer.json exists)
RUN if [ -f "composer.json" ]; then composer install --no-dev --prefer-dist; fi

# Set ownership and permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
