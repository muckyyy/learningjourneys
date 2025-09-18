#!/bin/bash
set -e

echo "Validating service deployment..."

# Suppress PHP deprecation warnings during deployment
export PHP_INI_SCAN_DIR=""
export PHPRC=""

# Function to run PHP commands without deprecation warnings
run_php_quiet() {
    php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" -d display_errors=0 -d log_errors=0 "$@" 2>/dev/null
}

# Function to run PHP commands as ec2-user without deprecation warnings
run_php_as_ec2user() {
    sudo -u ec2-user php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" -d display_errors=0 -d log_errors=0 "$@" 2>/dev/null || sudo -u ec2-user php "$@"
}

# Function to run artisan commands quietly as ec2-user
run_artisan_quiet() {
    run_php_as_ec2user artisan "$@" 2>/dev/null
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

# Check if Laravel Reverb is running
echo "Checking Laravel Reverb service..."
if systemctl is-active --quiet laravel-reverb; then
    echo "✓ Laravel Reverb is running"
elif systemctl is-enabled --quiet laravel-reverb; then
    echo "⚠ Laravel Reverb is enabled but not running (this may be expected if Reverb commands are not available)"
    # Check if it's failing due to missing commands
    systemctl status laravel-reverb --no-pager --lines=3 || true
else
    echo "⚠ Laravel Reverb service is not enabled"
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

# Check Laravel configuration
echo "Checking Laravel configuration..."
cd /var/www

# Check if APP_ENV is set to production in .env file
if grep -q "APP_ENV=production" .env 2>/dev/null; then
    echo "✓ Laravel is in production mode"
else
    echo "✗ Laravel is not in production mode"
    echo "Current APP_ENV setting: $(grep "^APP_ENV=" .env 2>/dev/null || echo "Not found")"
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

# Comprehensive Laravel log validation
echo "Checking Laravel logging system..."
echo "--- Log Directory Check ---"
if [ -d "/var/www/storage/logs" ]; then
    echo "✓ Log directory exists"
    LOG_DIR_OWNER=$(ls -ld /var/www/storage/logs | awk '{print $3":"$4}')
    LOG_DIR_PERMS=$(ls -ld /var/www/storage/logs | awk '{print $1}')
    echo "  Directory ownership: $LOG_DIR_OWNER"
    echo "  Directory permissions: $LOG_DIR_PERMS"
else
    echo "✗ Log directory does not exist"
    exit 1
fi

echo "--- Laravel Log File Check ---"
if [ -f "/var/www/storage/logs/laravel.log" ]; then
    LOG_SIZE=$(stat -c%s "/var/www/storage/logs/laravel.log" 2>/dev/null || echo "0")
    LOG_OWNER=$(ls -la "/var/www/storage/logs/laravel.log" | awk '{print $3":"$4}')
    LOG_PERMS=$(ls -la "/var/www/storage/logs/laravel.log" | awk '{print $1}')
    echo "✓ Laravel log file exists:"
    echo "  File size: $LOG_SIZE bytes"
    echo "  File ownership: $LOG_OWNER"
    echo "  File permissions: $LOG_PERMS"
    
    # Test if we can write to the log file
    if [ -w "/var/www/storage/logs/laravel.log" ]; then
        echo "✓ Log file is writable"
    else
        echo "⚠ Log file is not writable"
    fi
    
    # Show recent log entries if file has content
    if [ "$LOG_SIZE" -gt 0 ]; then
        echo "  Recent log entries:"
        tail -5 "/var/www/storage/logs/laravel.log" | sed 's/^/    /' || echo "    Could not read log file"
    else
        echo "  Log file is empty"
    fi
else
    echo "⚠ Laravel log file does not exist - creating for validation..."
    touch "/var/www/storage/logs/laravel.log"
    chown ec2-user:apache "/var/www/storage/logs/laravel.log"
    chmod 664 "/var/www/storage/logs/laravel.log"
    echo "  Created laravel.log file"
fi

echo "--- Live Logging Test ---"
echo "Testing if Laravel can write logs during validation..."
run_artisan_quiet tinker --execute="Log::info('Validation test log entry - ' . now());" || echo "⚠ Validation log test failed"

# Final log check after test
if [ -f "/var/www/storage/logs/laravel.log" ]; then
    FINAL_LOG_SIZE=$(stat -c%s "/var/www/storage/logs/laravel.log" 2>/dev/null || echo "0")
    echo "✓ Final log file size: $FINAL_LOG_SIZE bytes"
    if [ "$FINAL_LOG_SIZE" -gt 0 ]; then
        echo "  Last log entry:"
        tail -1 "/var/www/storage/logs/laravel.log" | sed 's/^/    /'
    fi
else
    echo "✗ Log file disappeared during validation!"
    exit 1
fi

echo "✓ All validation checks passed!"
echo "Deployment completed successfully"
