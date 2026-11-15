#!/bin/sh
set -e

echo "Fixing uploads and var directory permissions..."
mkdir -p /var/www/html/public/uploads/clients /var/www/html/var/cache /var/www/html/var/log /var/www/html/var/backups
chmod -R 775 /var/www/html/public/uploads /var/www/html/var
chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/var

echo "Running database migrations..."
php /var/www/html/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "$APP_ENV" = "prod" ]; then
    echo "Warming up production cache..."
    php /var/www/html/bin/console cache:clear --no-interaction || true
    php /var/www/html/bin/console cache:warmup --no-interaction || true
    chown -R www-data:www-data /var/www/html/var/cache
fi

echo "Starting application..."
exec "$@"
