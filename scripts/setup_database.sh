#!/bin/bash
set -e

APP_DIR="/var/www/learningjourneys"
ENV_FILE="$APP_DIR/.env"

echo "Setting up database..."

cd $APP_DIR

# Get database password from environment file
DB_PASSWORD=$(grep "^DB_PASSWORD=" $ENV_FILE | cut -d '"' -f2)

# Ensure MariaDB is running
systemctl start mariadb
systemctl enable mariadb

# Wait for MariaDB to be ready
echo "Waiting for MariaDB to be ready..."
until mysql -u root -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; do
    if mysql -u root -e "SELECT 1" &> /dev/null; then
        # If we can connect without password, set the password
        echo "Setting MariaDB root password..."
        mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; FLUSH PRIVILEGES;" || true
    fi
    sleep 2
done

# Create database if it doesn't exist
echo "Creating database if not exists..."
mysql -u root -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS learningjourneys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Grant privileges
mysql -u root -p"$DB_PASSWORD" -e "GRANT ALL PRIVILEGES ON learningjourneys.* TO 'root'@'localhost'; FLUSH PRIVILEGES;"

# Run Laravel migrations
echo "Running database migrations..."
php artisan migrate --force

# Seed database if needed (uncomment if you have seeders)
# echo "Seeding database..."
# php artisan db:seed --force

# Clear and cache configuration
echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Database setup completed"
