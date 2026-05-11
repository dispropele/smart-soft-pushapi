#!/bin/sh
set -e

# Set default values if not provided
export DATABASE_HOST=${DATABASE_HOST:-localhost}
export DATABASE_PORT=${DATABASE_PORT:-5432}
export DATABASE_USER=${DATABASE_USER:-app}

echo "Waiting for database at $DATABASE_HOST:$DATABASE_PORT..."
max_attempts=30
attempt=1

while ! pg_isready -h "$DATABASE_HOST" -p "$DATABASE_PORT" -U "$DATABASE_USER" >/dev/null 2>&1; do
  if [ $attempt -ge $max_attempts ]; then
    echo "Failed to connect to database after $max_attempts attempts"
    exit 1
  fi
  echo "Attempt $attempt/$max_attempts: Unable to connect to $DATABASE_HOST:$DATABASE_PORT"
  attempt=$((attempt + 1))
  sleep 2
done

echo "Database is ready!"
echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction || true

echo "Clearing cache..."
php bin/console cache:clear --env=prod || true

echo "Starting services..."
php-fpm &
nginx -g "daemon off;"
