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

# Fix Apache MPM conflict at runtime before Apache starts
echo "Fixing Apache MPM configuration..."
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
if ! grep -q "^LoadModule mpm_prefork_module" /etc/apache2/mods-enabled/mpm_prefork.load 2>/dev/null; then
	ln -s /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
	ln -s /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
fi

# Add Symfony directory config to Apache if not already present
if ! grep -q "Directory /var/www/html/public" /etc/apache2/sites-enabled/000-default.conf; then
	cat /tmp/apache-symfony.conf >> /etc/apache2/sites-enabled/000-default.conf
fi

sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-enabled/000-default.conf

echo "Clearing cache..."
php bin/console cache:clear --no-warmup 2>&1 || echo "Warning: Cache clear failed (may retry at request time)"

echo "Verifying database connection..."
if [ -z "${DATABASE_URL:-}" ]; then
	echo "ERROR: DATABASE_URL environment variable not set!"
	echo "Please configure DATABASE_URL in Railway dashboard or environment"
	echo "Format: mysql://user:password@host:port/database"
	exit 1
fi

echo "Database URL: ${DATABASE_URL}" | sed 's/:.*@/:***@/g'  # Log URL with password redacted
echo ""

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || {
	echo "Warning: Migration command encountered an issue"
	# Don't exit - try to continue anyway
}

echo "Checking database connection..."
php bin/console doctrine:query:sql "SELECT 1" 2>&1 | head -5 || {
	echo "Warning: Database connection check failed"
}

echo ""
echo "Starting Apache..."
exec apache2-foreground
