#!/bin/sh
set -e

echo "Fixing uploads directory permissions..."
mkdir -p /var/www/html/public/uploads/clients
chmod -R 775 /var/www/html/public/uploads
chown -R www-data:www-data /var/www/html/public/uploads

echo "Running database migrations..."
php /var/www/html/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "Starting application..."
exec "$@"
