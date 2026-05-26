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

# Copy Apache config
COPY apache-symfony.conf /tmp/apache-symfony.conf

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions and ensure var directories exist with proper permissions
RUN mkdir -p /var/www/html/var/cache /var/www/html/var/log && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/var && \
    chmod -R 775 /var/www/html/var/cache /var/www/html/var/log

# Fix Apache MPM conflict: remove all MPM modules except prefork
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf && \
    a2enmod mpm_prefork rewrite

# Configure Apache
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-enabled/000-default.conf

# Expose port
EXPOSE 80

# Run entrypoint script
ENTRYPOINT ["/entrypoint.sh"]
