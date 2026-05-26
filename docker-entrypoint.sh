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

# Only run migrations if DATABASE_URL is provided
if [ -n "${DATABASE_URL:-}" ]; then
	echo "Running database migrations with retries..."
	MAX_RETRIES=12
	RETRY=0
	DELAY=5

	until php bin/console doctrine:migrations:migrate -n --no-interaction; do
		RETCODE=$?
		RETRY=$((RETRY+1))
		if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
			echo "Migrations failed after $RETRY attempts (exit code $RETCODE). Continuing startup to allow container to run." >&2
			break
		fi
		echo "Migration attempt $RETRY failed (exit code $RETRY). Retrying in $DELAY seconds..." >&2
		sleep $DELAY
	done
else
	echo "DATABASE_URL not set; skipping migrations"
fi

echo "Clearing cache..."
php bin/console cache:clear --no-warmup || true

echo "Starting Apache..."
exec apache2-foreground
