FROM php:8.2-apache

WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Allow Composer to run as root and use plugins
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_ALLOW_PLUGINS=1
ENV APP_ENV=prod

# Copy application
COPY . .

# Copy entrypoint script
COPY docker-entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Enable Apache modules and ensure only one MPM is loaded
RUN a2dismod mpm_event mpm_worker || true && \
    a2enmod mpm_prefork rewrite

# Configure Apache
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-enabled/000-default.conf

# Expose port
EXPOSE 80

# Run entrypoint script
ENTRYPOINT ["/entrypoint.sh"]
