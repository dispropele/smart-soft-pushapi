#!/bin/sh
set -e

echo "Waiting for database..."
while ! pg_isready -h "$DATABASE_HOST" -U "$DATABASE_USER"; do
  sleep 1
done

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction || true

echo "Clearing cache..."
php bin/console cache:clear --env=prod || true

echo "Starting services..."
php-fpm &
nginx -g "daemon off;"
