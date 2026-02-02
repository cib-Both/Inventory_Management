FROM php:8.2-cli

# Install system dependencies and Node.js
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev zip libicu-dev curl supervisor \
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
RUN chmod -R 775 storage bootstrap/cache public

# Create supervisor configuration for queue worker
RUN mkdir -p /etc/supervisor/conf.d
RUN echo "[program:laravel-worker]\n\
process_name=%(program_name)s_%(process_num)02d\n\
command=php /var/www/artisan queue:work --sleep=3 --tries=3 --max-time=3600\n\
autostart=true\n\
autorestart=true\n\
stopasgroup=true\n\
killasgroup=true\n\
user=root\n\
numprocs=1\n\
redirect_stderr=true\n\
stdout_logfile=/var/www/storage/logs/worker.log\n\
stopwaitsecs=3600" > /etc/supervisor/conf.d/laravel-worker.conf

# Expose Render port
EXPOSE 10000

# Start Laravel with queue worker
CMD php artisan key:generate --force && \
    php artisan storage:link && \
    php artisan config:clear && \
    php artisan cache:clear && \
    php artisan view:clear && \
    php artisan migrate --force && \
    (php artisan db:seed --force || true) && \
    supervisord -c /etc/supervisor/supervisord.conf && \
    php artisan serve --host=0.0.0.0 --port=10000 --no-reload