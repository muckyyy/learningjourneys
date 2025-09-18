#!/bin/bash

echo "=== Laravel Route Debugging ==="
echo "Timestamp: $(date)"
cd /var/www || { echo "Cannot access /var/www"; exit 1; }

echo ""
echo "--- Route Cache Status ---"
if [ -f "bootstrap/cache/routes-v7.php" ]; then
    echo "✓ Route cache file exists"
    ls -la bootstrap/cache/routes-v7.php
    echo "File size: $(stat -c%s bootstrap/cache/routes-v7.php) bytes"
else
    echo "⚠ Route cache file missing"
    echo "Available cache files:"
    ls -la bootstrap/cache/ | grep route || echo "No route cache files"
fi

echo ""
echo "--- Route List Check ---"
echo "Checking for preview-chat route:"
sudo -u ec2-user php artisan route:list --name=preview-chat 2>/dev/null || echo "Route not found via name filter"

echo ""
echo "All routes containing 'preview':"
sudo -u ec2-user php artisan route:list 2>/dev/null | grep -i preview || echo "No routes containing 'preview'"

echo ""
echo "All routes containing 'journey':"
sudo -u ec2-user php artisan route:list 2>/dev/null | grep -i journey | head -10 || echo "No routes containing 'journey'"

echo ""
echo "Total route count:"
ROUTE_COUNT=$(sudo -u ec2-user php artisan route:list --json 2>/dev/null | jq length 2>/dev/null || echo "Cannot count routes")
echo "Routes: $ROUTE_COUNT"

echo ""
echo "--- Manual Route Cache Rebuild ---"
echo "Clearing route cache..."
sudo -u ec2-user php artisan route:clear

echo "Rebuilding route cache..."
if sudo -u ec2-user php artisan route:cache; then
    echo "✓ Route cache rebuilt successfully"
else
    echo "✗ Route cache rebuild failed"
fi

echo ""
echo "--- Post-Rebuild Check ---"
if [ -f "bootstrap/cache/routes-v7.php" ]; then
    echo "✓ Route cache file exists after rebuild"
    echo "New file size: $(stat -c%s bootstrap/cache/routes-v7.php) bytes"
else
    echo "⚠ Route cache file still missing after rebuild"
fi

echo ""
echo "Checking preview-chat route after rebuild:"
sudo -u ec2-user php artisan route:list --name=preview-chat 2>/dev/null || echo "Still not found"

echo ""
echo "--- Route Definition Check ---"
echo "Checking if route is defined in routes files:"
grep -n "preview-chat" routes/*.php || echo "Route definition not found in routes files"

echo ""
echo "=== Route Debugging Complete ==="