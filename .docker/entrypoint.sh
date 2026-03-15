#!/bin/sh
set -e

echo "Running database migrations..."
php /var/www/html/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Starting application..."
exec "$@"
