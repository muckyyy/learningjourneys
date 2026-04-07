#!/bin/bash

# Script to configure Apache WebSocket proxy for Laravel Reverb with SSL
# This script should be run on the EC2 server

echo "=== Configuring Apache WebSocket Proxy for Laravel Reverb ==="
echo ""

# Check if Apache proxy modules are enabled
echo "1. Checking Apache proxy modules..."
if httpd -M 2>/dev/null | grep -q "proxy_module"; then
    echo "✓ proxy_module is loaded"
else
    echo "⚠ proxy_module is NOT loaded"
fi

if httpd -M 2>/dev/null | grep -q "proxy_http_module"; then
    echo "✓ proxy_http_module is loaded" 
else
    echo "⚠ proxy_http_module is NOT loaded"
fi

if httpd -M 2>/dev/null | grep -q "proxy_wstunnel_module"; then
    echo "✓ proxy_wstunnel_module is loaded"
else
    echo "⚠ proxy_wstunnel_module is NOT loaded - this is critical for WebSocket proxy"
fi

echo ""
echo "2. Enabling required Apache modules..."

# Enable proxy modules (some may already be enabled)
echo "Enabling proxy modules..."
echo "LoadModule proxy_module modules/mod_proxy.so" >> /etc/httpd/conf.modules.d/00-proxy.conf 2>/dev/null || echo "proxy_module config already exists"
echo "LoadModule proxy_http_module modules/mod_proxy_http.so" >> /etc/httpd/conf.modules.d/00-proxy.conf 2>/dev/null || echo "proxy_http_module config already exists"
echo "LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so" >> /etc/httpd/conf.modules.d/00-proxy.conf 2>/dev/null || echo "proxy_wstunnel_module config already exists"

echo ""
echo "3. Updating Apache configuration..."

# Copy the updated Apache configuration
if [ -f "/var/www/config/apache/learningjourneys.conf" ]; then
    echo "Copying updated Apache configuration..."
    cp /var/www/config/apache/learningjourneys.conf /etc/httpd/conf.d/learningjourneys.conf
    echo "✓ Apache configuration updated"
else
    echo "⚠ Source Apache configuration not found at /var/www/config/apache/learningjourneys.conf"
fi

echo ""
echo "4. Testing Apache configuration..."
if httpd -t; then
    echo "✓ Apache configuration test passed"
else
    echo "✗ Apache configuration test failed!"
    exit 1
fi

echo ""
echo "5. Restarting services..."

# Restart Apache
echo "Restarting Apache..."
if systemctl restart httpd; then
    echo "✓ Apache restarted successfully"
else
    echo "✗ Apache restart failed"
    exit 1
fi

# Restart Laravel Reverb to ensure clean state
echo "Restarting Laravel Reverb..."
if systemctl restart laravel-reverb; then
    echo "✓ Laravel Reverb restarted successfully"
else
    echo "⚠ Laravel Reverb restart failed - checking status..."
    systemctl status laravel-reverb --no-pager --lines=5
fi

echo ""
echo "6. Verifying services..."

# Check Apache status
if systemctl is-active --quiet httpd; then
    echo "✓ Apache is running"
else
    echo "✗ Apache is not running"
    exit 1
fi

# Check Reverb status
if systemctl is-active --quiet laravel-reverb; then
    echo "✓ Laravel Reverb is running"
else
    echo "⚠ Laravel Reverb is not running"
fi

echo ""
echo "7. Testing WebSocket proxy..."

# Test if the proxy is working
echo "Testing WebSocket proxy endpoint..."
curl -i -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: test" \
  --connect-timeout 5 "https://the-thinking-course.com/app/app-key" 2>/dev/null | head -5 || echo "WebSocket proxy test inconclusive"

echo ""
echo "=== Configuration Complete ==="
echo ""
echo "Next steps:"
echo "1. Your website should now be able to connect to WebSockets via HTTPS"
echo "2. WebSocket connections will use: wss://the-thinking-course.com/app/app-key"
echo "3. Apache will proxy these to the local Reverb server on port 8080"
echo "4. Test your preview-chat page to verify WebSocket connections work"
echo ""
echo "If issues persist, check:"
echo "- Apache error logs: tail -f /var/log/httpd/learningjourneys_ssl_error.log"
echo "- Reverb service logs: journalctl -u laravel-reverb -f"
echo "- Browser developer console for WebSocket connection errors"