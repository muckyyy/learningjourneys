#!/bin/bash
set -e

echo "Starting services..."

# Copy Apache virtual host configuration
echo "Configuring Apache virtual host..."
cp /var/www/config/apache/learningjourneys.conf /etc/httpd/conf.d/

# Enable required Apache modules
echo "Enabling Apache modules..."
if ! httpd -M 2>/dev/null | grep -q "rewrite_module"; then
    echo "LoadModule rewrite_module modules/mod_rewrite.so" >> /etc/httpd/conf/httpd.conf
fi

if ! httpd -M 2>/dev/null | grep -q "ssl_module"; then
    echo "LoadModule ssl_module modules/mod_ssl.so" >> /etc/httpd/conf/httpd.conf
fi

if ! httpd -M 2>/dev/null | grep -q "headers_module"; then
    echo "LoadModule headers_module modules/mod_headers.so" >> /etc/httpd/conf/httpd.conf
fi

# Test Apache configuration
echo "Testing Apache configuration..."
httpd -t

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
