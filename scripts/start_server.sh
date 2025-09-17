#!/bin/bash
set -e

echo "Starting services..."

# Debug: Show what files are actually deployed
echo "=== DEPLOYMENT DEBUG ==="
echo "Contents of /var/www:"
ls -la /var/www/ || echo "Cannot list /var/www"
echo ""
echo "Looking for critical files:"
echo "- artisan: $([ -f /var/www/artisan ] && echo "Present" || echo "MISSING")"
echo "- config dir: $([ -d /var/www/config ] && echo "Present" || echo "MISSING")"
echo "- app dir: $([ -d /var/www/app ] && echo "Present" || echo "MISSING")"
echo "- public dir: $([ -d /var/www/public ] && echo "Present" || echo "MISSING")"
echo "- scripts dir: $([ -d /var/www/scripts ] && echo "Present" || echo "MISSING")"
echo "- apache config: $([ -f /var/www/config/apache/learningjourneys.conf ] && echo "Present" || echo "MISSING")"
echo ""

# Copy Apache virtual host configuration
echo "Configuring Apache virtual host..."
if [ -f /var/www/config/apache/learningjourneys.conf ]; then
    cp /var/www/config/apache/learningjourneys.conf /etc/httpd/conf.d/
    echo "✓ Apache virtual host configuration copied"
else
    echo "ERROR: Apache configuration file not found at /var/www/config/apache/learningjourneys.conf"
    echo "Available config files:"
    find /var/www -name "*.conf" 2>/dev/null || echo "No .conf files found"
    exit 1
fi

# Enable required Apache modules
echo "Enabling Apache modules..."

# Check which modules need to be enabled (check once to avoid repeated config loading)
MODULES_LOADED=$(httpd -M 2>/dev/null || echo "")

if ! echo "$MODULES_LOADED" | grep -q "rewrite_module"; then
    echo "Enabling rewrite module..."
    echo "LoadModule rewrite_module modules/mod_rewrite.so" >> /etc/httpd/conf/httpd.conf
else
    echo "✓ rewrite module already loaded"
fi

if ! echo "$MODULES_LOADED" | grep -q "ssl_module"; then
    echo "Enabling SSL module..."
    echo "LoadModule ssl_module modules/mod_ssl.so" >> /etc/httpd/conf/httpd.conf
else
    echo "✓ ssl module already loaded"
fi

if ! echo "$MODULES_LOADED" | grep -q "headers_module"; then
    echo "Enabling headers module..."
    echo "LoadModule headers_module modules/mod_headers.so" >> /etc/httpd/conf/httpd.conf
else
    echo "✓ headers module already loaded"
fi

# Test Apache configuration (only once)
echo "Testing Apache configuration..."
if ! httpd -t 2>&1; then
    echo "ERROR: Apache configuration test failed"
    echo "Checking configuration files..."
    echo "Main config syntax:"
    httpd -S 2>&1 | head -20
    exit 1
fi

echo "✓ Apache configuration is valid"

# Start Apache
echo "Starting Apache..."
systemctl start httpd
systemctl enable httpd

# Install and start Laravel WebSocket service
echo "Installing Laravel WebSocket service..."
cp /var/www/config/systemd/laravel-websockets.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable laravel-websockets
systemctl start laravel-websockets

# Wait a moment for services to start
sleep 5

echo "Services started successfully"
