#!/bin/bash

echo "=== Laravel Log Monitor ==="
echo "Timestamp: $(date)"
echo ""

# Check log directory
echo "--- Log Directory Status ---"
if [ -d "/var/www/storage/logs" ]; then
    echo "✓ Log directory exists"
    ls -la /var/www/storage/logs/
    echo ""
    echo "Directory permissions:"
    ls -ld /var/www/storage/logs/
else
    echo "✗ Log directory missing"
fi

echo ""
echo "--- Log File Details ---"
if [ -f "/var/www/storage/logs/laravel.log" ]; then
    LOG_SIZE=$(stat -c%s "/var/www/storage/logs/laravel.log")
    echo "✓ Laravel log file exists"
    echo "Size: $LOG_SIZE bytes"
    echo "Permissions: $(ls -la /var/www/storage/logs/laravel.log)"
    echo ""
    
    if [ "$LOG_SIZE" -gt 0 ]; then
        echo "--- Recent Log Entries (last 10 lines) ---"
        tail -10 /var/www/storage/logs/laravel.log
    else
        echo "Log file is empty"
    fi
else
    echo "✗ Laravel log file missing"
    echo "Creating log file..."
    touch /var/www/storage/logs/laravel.log
    chown ec2-user:apache /var/www/storage/logs/laravel.log
    chmod 664 /var/www/storage/logs/laravel.log
    echo "✓ Log file created"
fi

echo ""
echo "--- Testing Log Write ---"
cd /var/www
if sudo -u ec2-user php artisan tinker --execute="Log::info('Monitor test - ' . now());" 2>/dev/null; then
    echo "✓ Successfully wrote test log entry"
    tail -1 /var/www/storage/logs/laravel.log
else
    echo "✗ Failed to write test log entry"
fi

echo ""
echo "--- Process Information ---"
echo "Processes that might affect logs:"
ps aux | grep -E "(apache|httpd|php|laravel)" | grep -v grep || echo "No relevant processes found"

echo ""
echo "--- Systemd Services ---"
echo "Laravel Reverb service status:"
systemctl status laravel-reverb --no-pager --lines=5 2>/dev/null || echo "Service not found or not accessible"

echo ""
echo "=== Monitor Complete ==="