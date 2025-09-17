#!/bin/bash

echo "=== QUICK FIX FOR OWNERSHIP AND APACHE ISSUES ==="
echo "This script fixes the immediate ownership and Apache configuration issues"

APP_DIR="/var/www"

echo ""
echo "1. Fixing file ownership to ec2-user:apache..."
sudo chown -R ec2-user:apache $APP_DIR
echo "✓ Ownership updated"

echo ""
echo "2. Fixing file permissions..."
sudo find $APP_DIR -type f -exec chmod 644 {} \;
sudo find $APP_DIR -type d -exec chmod 755 {} \;
sudo chmod -R 775 $APP_DIR/storage
sudo chmod -R 775 $APP_DIR/bootstrap/cache
sudo chmod +x $APP_DIR/artisan
echo "✓ Permissions updated"

echo ""
echo "3. Checking .env file..."
if grep -q "APP_ENV=local" $APP_DIR/.env; then
    echo "Updating APP_ENV to production..."
    sudo sed -i 's/APP_ENV=local/APP_ENV=production/' $APP_DIR/.env
    echo "✓ APP_ENV updated to production"
else
    echo "✓ APP_ENV is already set correctly"
fi

if grep -q "APP_DEBUG=true" $APP_DIR/.env; then
    echo "Updating APP_DEBUG to false..."
    sudo sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' $APP_DIR/.env
    echo "✓ APP_DEBUG updated to false"
else
    echo "✓ APP_DEBUG is already set correctly"
fi

echo ""
echo "4. Verifying Apache configuration..."
APACHE_CONF_SOURCE="$APP_DIR/config/apache/learningjourneys.conf"
APACHE_CONF_TARGET="/etc/httpd/conf.d/learningjourneys.conf"

if [ -f "$APACHE_CONF_SOURCE" ]; then
    echo "Copying Apache configuration..."
    sudo cp "$APACHE_CONF_SOURCE" "$APACHE_CONF_TARGET"
    sudo chown root:root "$APACHE_CONF_TARGET"
    sudo chmod 644 "$APACHE_CONF_TARGET"
    echo "✓ Apache configuration updated"
    
    echo "Testing Apache configuration..."
    if sudo httpd -t; then
        echo "✓ Apache configuration is valid"
        
        echo "Restarting Apache..."
        sudo systemctl restart httpd
        echo "✓ Apache restarted"
    else
        echo "✗ Apache configuration has errors"
    fi
else
    echo "⚠ Apache configuration source file not found: $APACHE_CONF_SOURCE"
fi

echo ""
echo "5. Final verification..."
echo "Current ownership of $APP_DIR:"
ls -ld $APP_DIR

echo ""
echo "DocumentRoot directory:"
ls -ld $APP_DIR/public

echo ""
echo "Apache status:"
systemctl status httpd --no-pager -l

echo ""
echo "=== QUICK FIX COMPLETED ==="