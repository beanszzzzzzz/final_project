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

# Ensure JWT_SECRET is set; generate a random one if missing
if [ -z "${JWT_SECRET:-}" ]; then
	echo "JWT_SECRET not set — generating secure secret for runtime"
	export JWT_SECRET=$(php -r "echo bin2hex(random_bytes(32));")
fi

# Railway assigns a runtime PORT; make Apache listen on it instead of the image default.
export PORT=${PORT:-8080}

wait_for_database() {
	local attempt=1
	local max_attempts=30

	while [ "$attempt" -le "$max_attempts" ]; do
		if php bin/console doctrine:query:sql "SELECT 1" >/dev/null 2>&1; then
			return 0
		fi

		echo "Waiting for database to become available... ($attempt/$max_attempts)"
		attempt=$((attempt + 1))
		sleep 2
	done

	echo "ERROR: Database did not become ready in time."
	return 1
}

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
# Ensure cache directories exist and are fully writable by www-data
mkdir -p /var/www/html/var/cache /var/www/html/var/log
chown -R www-data:www-data /var/www/html/var
chmod -R u+rwx,g+rwx,o+rx /var/www/html/var

# Try to clear cache - non-critical if it fails
php bin/console cache:clear --no-warmup 2>&1 || {
	echo "⚠️  Cache clear had issues, but continuing..."
	# Ensure directories are writable anyway
	chmod -R 777 /var/www/html/var/cache /var/www/html/var/log
}

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
wait_for_database

# First, check if migrations table exists, if not create it
echo "Initializing migration tracking..."
php bin/console doctrine:migrations:sync-metadata-storage --no-interaction 2>&1 || {
	echo "⚠️  Could not sync migration metadata"
}

# Try to run all migrations - but don't fail the entire startup
echo "Attempting to run pending migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || {
	echo "⚠️  Migrations had issues - attempting schema update fallback..."
	# If migrations fail, try to update schema directly
	php bin/console doctrine:schema:update --force --no-interaction 2>&1 || {
		echo "⚠️  Schema update also failed - app will attempt to continue"
	}
}

# Final check: Verify critical tables exist
echo "Verifying critical database tables..."
php bin/console doctrine:query:sql "SELECT 1 FROM user LIMIT 1" 2>&1 > /dev/null || {
	echo "⚠️  User table does not exist or is inaccessible"
	echo "Attempting to create via schema update..."
	php bin/console doctrine:schema:update --force --no-interaction 2>&1 || true
}

# Seed test users for development/testing
echo "Seeding test users..."
php bin/console app:seed:users --no-interaction 2>&1 || {
	echo "⚠️  Could not seed test users (may already exist)"
}

echo "Loading test data (fixtures)..."
# Fixtures bundle not available - skipping fixture loading
# The app can work with empty database
echo "⚠️  Fixtures bundle not configured - skipping fixture load"

echo "Checking database connection..."
php bin/console doctrine:query:sql "SELECT 1" 2>&1 | head -5 || {
	echo "Warning: Database connection check failed"
}

echo ""
echo "Final permission check before starting Apache..."
# Ensure ALL var subdirectories are writable by www-data
chown -R www-data:www-data /var/www/html/var
find /var/www/html/var -type d -exec chmod 777 {} \;
find /var/www/html/var -type f -exec chmod 666 {} \;

echo "Starting Apache..."
exec apache2-foreground
