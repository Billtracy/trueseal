# Use an official PHP 8.2 FPM image with Alpine for a smaller footprint, 
# but Debian/Ubuntu is often easier for Python+PHP hybrid environments.
FROM php:8.3-fpm

# Install system dependencies, Nginx, Supervisor, Python 3, Tesseract OCR
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    python3 \
    python3-pip \
    python3-venv \
    tesseract-ocr \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    sqlite3 \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_sqlite gd

# Install Node.js (for Vite asset compilation)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy the entire project
COPY . .

# -------------------------------
# Setup Python Environment
# -------------------------------
# Install python dependencies globally within the container using --break-system-packages 
# (safe in a dedicated Docker container)
RUN pip3 install --no-cache-dir -r python/requirements.txt --break-system-packages

# -------------------------------
# Setup Laravel Environment
# -------------------------------
WORKDIR /var/www/html/web

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Install NPM dependencies and build Vite assets
RUN npm install \
    && npm run build

# Touch sqlite database if using SQLite (so migrations don't fail)
RUN mkdir -p database \
    && touch database/database.sqlite

# Fix permissions
RUN chown -R www-data:www-data /var/www/html/web/storage /var/www/html/web/bootstrap/cache /var/www/html/web/database \
    && chmod -R 775 /var/www/html/web/storage /var/www/html/web/bootstrap/cache /var/www/html/web/database

# -------------------------------
# Configure Nginx & Supervisor
# -------------------------------
WORKDIR /var/www/html

# Copy Nginx config
RUN rm /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-enabled/default

# Copy Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy Start script and make it executable
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Expose port 8080 (DO App platform uses this port by default or allows configuration)
EXPOSE 8080

# Run the startup script
ENTRYPOINT ["/usr/local/bin/start.sh"]
