#!/bin/bash

echo "=== Laravel Log Troubleshooting Script ==="
echo "Timestamp: $(date)"
echo "User: $(whoami)"
echo "Working Directory: $(pwd)"
echo ""

cd /var/www || { echo "Cannot access /var/www"; exit 1; }

echo "--- Environment Check ---"
echo "APP_ENV: $(grep "^APP_ENV=" .env 2>/dev/null || echo "Not found")"
echo "LOG_CHANNEL: $(grep "^LOG_CHANNEL=" .env 2>/dev/null || echo "Not set (using default)")"
echo "LOG_LEVEL: $(grep "^LOG_LEVEL=" .env 2>/dev/null || echo "Not set (using default)")"
echo ""

echo "--- Storage Directory Structure ---"
echo "Storage directory contents:"
find storage/ -type d -exec ls -ld {} \; 2>/dev/null
echo ""
echo "All files in storage:"
find storage/ -type f -exec ls -la {} \; 2>/dev/null | head -20
echo ""

echo "--- Laravel Configuration ---"
echo "Laravel log configuration:"
sudo -u ec2-user php artisan config:show logging.default 2>/dev/null || echo "Cannot show config"
sudo -u ec2-user php artisan config:show logging.channels.single.path 2>/dev/null || echo "Cannot show single channel path"
echo ""

echo "--- Manual Log Test ---"
echo "Attempting to create log entry manually:"
if sudo -u ec2-user php artisan tinker --execute="Log::info('Manual troubleshoot test - ' . now()->toDateTimeString());" 2>/dev/null; then
    echo "✓ Successfully created log entry"
else
    echo "✗ Failed to create log entry"
fi
echo ""

echo "--- Immediate File Check ---"
echo "Checking for log files immediately after test:"
ls -la storage/logs/ 2>/dev/null || echo "Logs directory not accessible or empty"
find . -name "*.log" -type f -exec ls -la {} \; 2>/dev/null || echo "No .log files found anywhere"
echo ""

echo "--- Process and Service Check ---"
echo "Laravel Reverb service:"
systemctl status laravel-reverb --no-pager --lines=3 2>/dev/null || echo "Cannot check Reverb service"
echo ""
echo "Processes using log files:"
lsof +D storage/logs/ 2>/dev/null || echo "No processes using log files (or lsof not available)"
echo ""

echo "--- System Logs ---"
echo "Recent system messages related to Laravel or logging:"
journalctl --since "10 minutes ago" | grep -i -E "(laravel|log|storage)" | tail -10 || echo "No recent system log entries"
echo ""

echo "--- Permissions Deep Dive ---"
echo "Full storage permissions:"
ls -laR storage/ 2>/dev/null | head -50
echo ""

echo "--- Test Log Creation as Different Users ---"
echo "Testing log creation as ec2-user:"
sudo -u ec2-user touch storage/logs/test-ec2user.log 2>/dev/null && echo "✓ ec2-user can create files" || echo "✗ ec2-user cannot create files"

echo "Testing log creation as apache:"
sudo -u apache touch storage/logs/test-apache.log 2>/dev/null && echo "✓ apache can create files" || echo "✗ apache cannot create files"

echo "Current file status:"
ls -la storage/logs/test-*.log 2>/dev/null || echo "Test files not found"

# Cleanup test files
rm -f storage/logs/test-*.log 2>/dev/null

echo ""
echo "=== Troubleshooting Complete ==="