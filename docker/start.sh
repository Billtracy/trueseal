#!/bin/sh

# Set correct permissions
chown -R www-data:www-data /var/www/html/web/storage /var/www/html/web/bootstrap/cache /var/www/html/web/database

# Run Laravel migrations (force for production/container)
cd /var/www/html/web
php artisan migrate --force

# Ensure database permissions are still correct after migration
chown -R www-data:www-data /var/www/html/web/database

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Supervisor (which starts Nginx and PHP-FPM)
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
