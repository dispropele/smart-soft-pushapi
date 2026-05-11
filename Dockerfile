# Build stage
from php:8.4-fpm-alpine as builder

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpq-dev \
    postgresql-client \
    oniguruma-dev \
    libzip-dev \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    opcache \
    zip

# Copy opcache configuration
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy only composer.json
COPY composer.json ./

# Install PHP dependencies (this will create composer.lock)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Generate cache for production
RUN mkdir -p var/cache var/log && chmod -R 777 var

# Runtime stage
from php:8.4-fpm-alpine

# Install runtime dependencies (no build tools)
RUN apk add --no-cache \
    nginx \
    libpq \
    postgresql-client \
    curl

# Copy PHP extensions and configuration from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Enable PHP extensions
RUN docker-php-ext-enable pdo pdo_pgsql opcache

# Copy PHP-FPM configuration
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf

# Copy application from builder
COPY --from=builder --chown=www-data:www-data /app /app

WORKDIR /app

# Create necessary directories
RUN mkdir -p var/cache var/log public/uploads && \
    chmod -R 777 var public/uploads

# Copy Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Create startup script
RUN echo '#!/bin/sh\n\
set -e\n\
\n\
echo "Waiting for database..."\n\
while ! pg_isready -h $DATABASE_HOST -U $DATABASE_USER; do\n\
  sleep 1\n\
done\n\
\n\
echo "Running migrations..."\n\
php bin/console doctrine:migrations:migrate --no-interaction || true\n\
\n\
echo "Clearing cache..."\n\
php bin/console cache:clear --env=prod || true\n\
\n\
echo "Starting services..."\n\
php-fpm &\n\
nginx -g "daemon off;"\n\
' > /entrypoint.sh && chmod +x /entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Use dynamic port from Render
EXPOSE 8080

# Environment variables
ENV APP_ENV=prod \
    DATABASE_HOST=localhost \
    DATABASE_PORT=5432 \
    DATABASE_NAME=app \
    DATABASE_USER=app

ENTRYPOINT ["/entrypoint.sh"]
