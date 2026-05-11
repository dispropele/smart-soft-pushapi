#!/bin/sh
set -e

# Set default values if not provided
export DATABASE_HOST=${DATABASE_HOST:-localhost}
export DATABASE_PORT=${DATABASE_PORT:-5432}
export DATABASE_USER=${DATABASE_USER:-app}

echo "Database configuration:"
echo "  HOST: $DATABASE_HOST"
echo "  PORT: $DATABASE_PORT"
echo "  USER: $DATABASE_USER"
echo ""

# Only wait for database if we're not using localhost
# (localhost means variables were not properly set from Render)
if [ "$DATABASE_HOST" != "localhost" ]; then
  echo "Waiting for database at $DATABASE_HOST:$DATABASE_PORT..."
  max_attempts=30
  attempt=1

  while ! pg_isready -h "$DATABASE_HOST" -p "$DATABASE_PORT" -U "$DATABASE_USER" >/dev/null 2>&1; do
    if [ $attempt -ge $max_attempts ]; then
      echo "Failed to connect to database after $max_attempts attempts"
      break
    fi
    echo "Attempt $attempt/$max_attempts: Unable to connect..."
    attempt=$((attempt + 1))
    sleep 2
  done
  echo "Database connection successful!"
else
  echo "WARNING: Using default database host (localhost). Please set DATABASE_HOST environment variable."
fi

echo ""
echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction || echo "Migrations skipped or failed (database may not be ready yet)"

echo "Clearing cache..."
php bin/console cache:clear --env=prod || echo "Cache clear skipped"

echo ""
echo "Starting services..."
echo "PHP-FPM will start on 127.0.0.1:9000"
echo "Nginx will listen on 0.0.0.0:8080"
echo ""

php-fpm &
nginx -g "daemon off;"
