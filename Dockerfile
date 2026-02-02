FROM php:8.2-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev zip libicu-dev curl \
    && docker-php-ext-install pdo pdo_pgsql zip intl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy project files
COPY . .

# Create .env file from .env.example
RUN cp .env.example .env

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install NPM dependencies and build Vite assets
RUN npm install && npm run build

# Create necessary storage directories
RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache/data \
    storage/logs \
    bootstrap/cache \
    public/storage

# Set permissions
RUN chmod -R 775 storage bootstrap/cache public/storage

# Expose Render port
EXPOSE 10000

# Start Laravel - generate key at runtime when env vars are available
CMD php artisan key:generate --force && \
    php artisan config:clear && \
    php artisan cache:clear && \
    php artisan view:clear && \
    php artisan migrate --force && \
    php artisan db:seed --force && \
    php artisan serve --host=0.0.0.0 --port=10000 --no-reload