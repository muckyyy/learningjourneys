#!/bin/bash
set -e

echo "Validating service deployment..."

# Check if Apache is running
echo "Checking Apache service..."
if systemctl is-active --quiet httpd; then
    echo "✓ Apache is running"
else
    echo "✗ Apache is not running"
    exit 1
fi

# Check if MariaDB is running
echo "Checking MariaDB service..."
if systemctl is-active --quiet mariadb; then
    echo "✓ MariaDB is running"
else
    echo "✗ MariaDB is not running"
    exit 1
fi

# Check if Laravel WebSockets is running
echo "Checking Laravel WebSockets service..."
if systemctl is-active --quiet laravel-websockets; then
    echo "✓ Laravel WebSockets is running"
else
    echo "✗ Laravel WebSockets is not running"
    exit 1
fi

# Check if web application is accessible
echo "Checking web application accessibility..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ || echo "000")
if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ] || [ "$HTTP_STATUS" = "301" ]; then
    echo "✓ Web application is accessible (HTTP $HTTP_STATUS)"
else
    echo "✗ Web application is not accessible (HTTP $HTTP_STATUS)"
    exit 1
fi

# Check database connection
echo "Checking database connection..."
cd /var/www
if php artisan migrate:status &>/dev/null; then
    echo "✓ Database connection is working"
else
    echo "✗ Database connection failed"
    exit 1
fi

# Check Laravel configuration
echo "Checking Laravel configuration..."
if php artisan config:show app.env | grep -q "production"; then
    echo "✓ Laravel is in production mode"
else
    echo "✗ Laravel is not in production mode"
fi

# Check file permissions
echo "Checking file permissions..."
if [ -w "/var/www/storage" ]; then
    echo "✓ Storage directory is writable"
else
    echo "✗ Storage directory is not writable"
    exit 1
fi

if [ -w "/var/www/bootstrap/cache" ]; then
    echo "✓ Bootstrap cache directory is writable"
else
    echo "✗ Bootstrap cache directory is not writable"
    exit 1
fi

echo "✓ All validation checks passed!"
echo "Deployment completed successfully"
