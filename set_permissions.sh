#!/bin/bash
set -e

APP_DIR="/var/www"

echo "Setting file permissions..."

cd $APP_DIR

# Set ownership
echo "Setting ownership to apache:apache..."
chown -R apache:apache $APP_DIR

# Set base permissions
echo "Setting base file and directory permissions..."
find $APP_DIR -type f -exec chmod 644 {} \;
find $APP_DIR -type d -exec chmod 755 {} \;

# Set executable permissions for artisan and scripts
echo "Setting executable permissions..."
chmod +x $APP_DIR/artisan
chmod +x $APP_DIR/*.sh

# Set writable permissions for Laravel directories
echo "Setting writable permissions for Laravel directories..."
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

# Ensure proper permissions for logs
if [ -d "$APP_DIR/storage/logs" ]; then
    chmod -R 775 $APP_DIR/storage/logs
fi

# Set secure permissions for .env file
if [ -f "$APP_DIR/.env" ]; then
    echo "Setting secure permissions for .env file..."
    chmod 600 $APP_DIR/.env
    chown apache:apache $APP_DIR/.env
fi

# Set permissions for public directory
if [ -d "$APP_DIR/public" ]; then
    chmod -R 755 $APP_DIR/public
fi

# Ensure Apache can write to session and cache directories
if [ -d "$APP_DIR/storage/framework/sessions" ]; then
    chmod -R 775 $APP_DIR/storage/framework/sessions
fi

if [ -d "$APP_DIR/storage/framework/cache" ]; then
    chmod -R 775 $APP_DIR/storage/framework/cache
fi

if [ -d "$APP_DIR/storage/framework/views" ]; then
    chmod -R 775 $APP_DIR/storage/framework/views
fi

echo "File permissions set successfully"