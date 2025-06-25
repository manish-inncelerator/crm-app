FROM php:8.2-apache

# Enable mod_rewrite if needed
RUN a2enmod rewrite

# Copy project files to Apache web root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Set file permissions (optional but good practice)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
