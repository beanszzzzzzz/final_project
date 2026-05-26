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

# Copy application
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Enable Apache modules
RUN a2enmod rewrite

# Configure Apache
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-enabled/000-default.conf

# Expose port
EXPOSE 80

# Run migrations and start Apache
CMD ["php", "bin/console", "doctrine:migrations:migrate", "-n", "--no-interaction"]
