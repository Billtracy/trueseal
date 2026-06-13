#!/bin/sh

# Set correct permissions
chown -R www-data:www-data /var/www/html/web/storage /var/www/html/web/bootstrap/cache

# Run Laravel migrations (force for production/container)
cd /var/www/html/web
php artisan migrate --force

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Supervisor (which starts Nginx and PHP-FPM)
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
