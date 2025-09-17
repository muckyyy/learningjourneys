#!/bin/bash
set -e

echo "Validating service deployment..."

# Function to run PHP commands without deprecation warnings
run_php_quiet() {
    php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" "$@" 2>/dev/null || php "$@"
}

# Function to run artisan commands quietly
run_artisan_quiet() {
    run_php_quiet artisan "$@"
}

# Check if Apache is running
echo "Checking Apache service..."
if systemctl is-active --quiet httpd; then
    echo "✓ Apache is running"
    
    # Check if Apache configuration is properly loaded
    echo "Checking Apache configuration..."
    if [ -f "/etc/httpd/conf.d/learningjourneys.conf" ]; then
        echo "✓ Apache configuration file exists"
    else
        echo "⚠ Apache configuration file missing at /etc/httpd/conf.d/learningjourneys.conf"
        if [ -f "/var/www/config/apache/learningjourneys.conf" ]; then
            echo "  Source config found at /var/www/config/apache/learningjourneys.conf"
        fi
    fi
    
    # Check if DocumentRoot exists and is accessible
    if [ -d "/var/www/public" ]; then
        echo "✓ DocumentRoot (/var/www/public) exists"
        OWNER_INFO=$(ls -ld /var/www/public | awk '{print $3":"$4}')
        echo "  DocumentRoot ownership: $OWNER_INFO"
    else
        echo "✗ DocumentRoot (/var/www/public) does not exist"
    fi
else
    echo "✗ Apache is not running"
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
# echo "Checking web application accessibility..."
# HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/ || echo "000")
# if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ] || [ "$HTTP_STATUS" = "301" ]; then
#    echo "✓ Web application is accessible (HTTP $HTTP_STATUS)"
#else
#    echo "✗ Web application is not accessible (HTTP $HTTP_STATUS)"
#    exit 1
#fi 

# Check database connection
echo "Checking database connection..."
cd /var/www
if run_artisan_quiet migrate:status &>/dev/null; then
    echo "✓ Database connection is working"
else
    echo "✗ Database connection failed"
    exit 1
fi

# Check Laravel configuration
echo "Checking Laravel configuration..."
cd /var/www
if run_artisan_quiet config:show app.env 2>/dev/null | grep -q "production"; then
    echo "✓ Laravel is in production mode"
else
    echo "✗ Laravel is not in production mode"
    if grep -q "APP_ENV=production" .env; then
        echo "✓ APP_ENV is set to production in .env file"
    else
        echo "✗ APP_ENV is not set to production in .env file"
        echo "Current APP_ENV setting: $(grep "^APP_ENV=" .env || echo "Not found")"
    fi
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
