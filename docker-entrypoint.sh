#!/bin/bash
set -e

echo "Preparing runtime environment..."

# Ensure APP_ENV defaults to prod during runtime if not set
export APP_ENV=${APP_ENV:-prod}

# Ensure APP_SECRET is set; generate a random one if missing to allow Symfony to boot
if [ -z "${APP_SECRET:-}" ]; then
	echo "APP_SECRET not set — generating temporary secret for runtime"
	export APP_SECRET=$(php -r "echo bin2hex(random_bytes(16));")
fi

# Railway assigns a runtime PORT; make Apache listen on it instead of the image default.
export PORT=${PORT:-8080}
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf

echo "Clearing cache..."
php bin/console cache:clear --no-warmup || true

echo "Starting Apache..."
exec apache2-foreground
