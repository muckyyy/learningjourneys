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

# Remove old Laravel WebSocket service if it exists
echo "Checking for old Laravel WebSocket service..."
if systemctl is-enabled laravel-websockets 2>/dev/null; then
    echo "Found old Laravel WebSocket service, removing..."
    systemctl stop laravel-websockets || true
    systemctl disable laravel-websockets || true
    rm -f /etc/systemd/system/laravel-websockets.service
    systemctl daemon-reload
    echo "✓ Old Laravel WebSocket service removed"
else
    echo "✓ No old Laravel WebSocket service found"
fi

# Install and start Laravel Reverb service
echo "Installing Laravel Reverb service..."
cp /var/www/config/systemd/laravel-reverb.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable laravel-reverb

# Check if Reverb commands are available before starting the service
echo "Checking if Reverb is available..."
cd /var/www
if php artisan list | grep -q "reverb:"; then
    echo "✓ Reverb commands available, starting service..."
    systemctl start laravel-reverb
    echo "✓ Laravel Reverb service started"
else
    echo "⚠ Reverb commands not available, skipping Reverb service startup"
    echo "Available commands:"
    php artisan list | grep -E "(websocket|broadcast|reverb)" || echo "No websocket/broadcast related commands found"
fi

# Install and start Laravel Queue Worker instances (8 parallel workers)
QUEUE_WORKERS=8
echo "Installing Laravel Queue Workers ($QUEUE_WORKERS instances)..."
if [ -f /var/www/config/systemd/laravel-queue@.service ]; then
    cp /var/www/config/systemd/laravel-queue@.service /etc/systemd/system/
    systemctl daemon-reload
    for i in $(seq 1 $QUEUE_WORKERS); do
        systemctl enable laravel-queue@${i}
        systemctl restart laravel-queue@${i}
    done
    echo "✓ $QUEUE_WORKERS Laravel Queue Workers started"
else
    echo "⚠ Queue worker template service file not found"
fi

# Install and start Laravel Pulse server monitoring
echo "Installing Laravel Pulse monitor..."
if [ -f /var/www/config/systemd/laravel-pulse.service ]; then
    cp /var/www/config/systemd/laravel-pulse.service /etc/systemd/system/
    systemctl daemon-reload
    systemctl enable laravel-pulse
    systemctl restart laravel-pulse
    echo "✓ Laravel Pulse monitor started"
else
    echo "⚠ Pulse service file not found"
fi

# Wait a moment for services to start
sleep 10

# =============================================================================
# FIX PERMISSIONS: artisan commands above ran as root, fix ownership
# =============================================================================
echo "Fixing file permissions after service setup..."
chown -R apache:apache /var/www/storage
chown -R apache:apache /var/www/bootstrap/cache
chown -R apache:apache /var/www/resources
chmod -R 775 /var/www/storage
chmod -R 775 /var/www/bootstrap/cache
echo "✓ Permissions fixed"

echo "Services started successfully"
