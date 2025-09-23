#!/bin/bash

echo "=== Post-Deployment Log Preservation ==="
echo "Timestamp: $(date)"
echo "Preserving deployment state for debugging..."

cd /var/www

# Create a backup of current log state
mkdir -p /tmp/laravel-log-debug
cp -r storage/logs/* /tmp/laravel-log-debug/ 2>/dev/null || echo "No logs to backup"

# Log current state
echo "=== POST-DEPLOYMENT STATE ===" > /tmp/laravel-log-debug/deployment-state.txt
echo "Date: $(date)" >> /tmp/laravel-log-debug/deployment-state.txt
echo "User: $(whoami)" >> /tmp/laravel-log-debug/deployment-state.txt
echo "" >> /tmp/laravel-log-debug/deployment-state.txt
echo "Storage directory:" >> /tmp/laravel-log-debug/deployment-state.txt
ls -la storage/logs/ >> /tmp/laravel-log-debug/deployment-state.txt 2>&1
echo "" >> /tmp/laravel-log-debug/deployment-state.txt
echo "All log files in system:" >> /tmp/laravel-log-debug/deployment-state.txt
find /var/www -name "*.log" -type f -exec ls -la {} \; >> /tmp/laravel-log-debug/deployment-state.txt 2>&1

# Try to create a test log after deployment
echo "Testing post-deployment logging:" >> /tmp/laravel-log-debug/deployment-state.txt
if sudo -u apache php artisan tinker --execute="Log::info('POST DEPLOYMENT TEST - ' . now());" 2>&1; then
    echo "✓ Post-deployment logging successful" >> /tmp/laravel-log-debug/deployment-state.txt
    ls -la storage/logs/ >> /tmp/laravel-log-debug/deployment-state.txt 2>&1
else
    echo "✗ Post-deployment logging failed" >> /tmp/laravel-log-debug/deployment-state.txt
fi

echo "Debug information saved to /tmp/laravel-log-debug/"
echo "To view: cat /tmp/laravel-log-debug/deployment-state.txt"

echo "=== Post-Deployment Hook Complete ==="