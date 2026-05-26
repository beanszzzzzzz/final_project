#!/bin/bash
set -e

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
	echo "Migration attempt $RETRY failed (exit code $RETCODE). Retrying in $DELAY seconds..." >&2
	sleep $DELAY
done

echo "Clearing cache..."
php bin/console cache:clear --no-warmup || true

echo "Starting Apache..."
exec apache2-foreground
