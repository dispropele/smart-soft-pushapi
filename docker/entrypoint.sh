#!/bin/sh
set -e

# Set default values if not provided
export DATABASE_HOST=${DATABASE_HOST:-localhost}
export DATABASE_PORT=${DATABASE_PORT:-5432}
export DATABASE_USER=${DATABASE_USER:-app}
export DATABASE_NAME=${DATABASE_NAME:-app}
export DATABASE_PASSWORD=${DATABASE_PASSWORD:-}

# If DATABASE_URL is not set, build it from DATABASE_* variables
if [ -z "$DATABASE_URL" ]; then
  if [ -n "$DATABASE_PASSWORD" ]; then
    export DATABASE_URL="postgresql://$DATABASE_USER:$DATABASE_PASSWORD@$DATABASE_HOST:$DATABASE_PORT/$DATABASE_NAME?serverVersion=18&charset=utf8"
  else
    export DATABASE_URL="postgresql://$DATABASE_USER@$DATABASE_HOST:$DATABASE_PORT/$DATABASE_NAME?serverVersion=18&charset=utf8"
  fi
fi

echo "=== Container Startup ===" 
echo "Database configuration:"
echo "  HOST: $DATABASE_HOST"
echo "  PORT: $DATABASE_PORT"
echo "  USER: $DATABASE_USER"
echo ""

# Only wait for database if we're not using localhost
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
echo "=== Running Pre-startup Tasks ==="

# Try to run migrations
echo "Running database migrations..."
if php bin/console doctrine:migrations:migrate --no-interaction 2>&1; then
  echo "✓ Migrations completed successfully"
else
  echo "⚠ Migrations failed or database not ready (will retry on requests)"
fi

# Optional: run database seed command when RUN_SEEDS=1
export RUN_SEEDS=${RUN_SEEDS:-0}
export SEED_NO_CLEAR=${SEED_NO_CLEAR:-1}
if [ "$RUN_SEEDS" = "1" ]; then
  echo "Running database seed (RUN_SEEDS=1)..."
  if [ "$SEED_NO_CLEAR" = "1" ]; then
    php bin/console app:seed --no-clear || echo "⚠ Seed failed"
  else
    php bin/console app:seed || echo "⚠ Seed failed"
  fi
fi

# Optional: create admin user if credentials provided
if [ -n "$ADMIN_USERNAME" ] && [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
  echo "Creating admin user: $ADMIN_USERNAME"
  php bin/console app:create-admin "$ADMIN_USERNAME" "$ADMIN_EMAIL" "$ADMIN_PASSWORD" || echo "⚠ Admin creation failed"
fi

# Clear cache
echo "Clearing application cache..."
if php bin/console cache:clear --env=prod 2>&1; then
  echo "✓ Cache cleared"
else
  echo "⚠ Cache clear failed"
fi

echo "Fixing permissions for cache and logs..."
chown -R www-data:www-data /app/var /app/public/uploads

echo ""
echo "=== Starting Services ==="

echo ""
echo "=== Starting Services ==="
echo "PHP-FPM will listen on /run/php-fpm.sock"
echo "Nginx will listen on 0.0.0.0:8080"
echo ""

# Create run directory for PHP-FPM socket
mkdir -p /run

# Start PHP-FPM in the background
echo "Starting PHP-FPM..."
php-fpm -F &
PHP_FPM_PID=$!
sleep 2

# Give PHP-FPM time to create the socket
sleep 1

# Check if socket was created
if [ ! -S /run/php-fpm.sock ]; then
  echo "WARNING: PHP-FPM socket not created, trying to start nginx anyway..."
else
  echo "✓ PHP-FPM socket created successfully"
fi

echo "✓ PHP-FPM started (PID: $PHP_FPM_PID)"

# Start Nginx and keep it in foreground
echo "Starting Nginx..."
exec nginx -g "daemon off;"
