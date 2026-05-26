#!/bin/bash
set -e

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate -n --no-interaction || true

echo "Clearing cache..."
php bin/console cache:clear --no-warmup || true

echo "Starting Apache..."
exec apache2-foreground
