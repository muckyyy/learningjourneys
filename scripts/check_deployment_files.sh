#!/bin/bash

echo "=== CodeDeploy File Deployment Check ==="

echo "1. Checking /var/www directory:"
if [ -d "/var/www" ]; then
    echo "✓ /var/www exists"
    echo "Contents:"
    sudo ls -la /var/www/
    echo ""
    echo "Directory size:"
    sudo du -sh /var/www/ 2>/dev/null
else
    echo "✗ /var/www does not exist"
fi

echo ""
echo "2. Looking for Laravel files:"
sudo find /var/www -name "artisan" -type f 2>/dev/null && echo "✓ Laravel artisan found" || echo "✗ Laravel artisan not found"
sudo find /var/www -name "composer.json" -type f 2>/dev/null && echo "✓ composer.json found" || echo "✗ composer.json not found"
sudo find /var/www -name "*.env*" -type f 2>/dev/null && echo "✓ Environment files found" || echo "✗ No environment files found"

echo ""
echo "3. Looking for deployment scripts:"
sudo find /var/www -name "*.sh" -type f 2>/dev/null | head -10

echo ""
echo "4. Checking recent CodeDeploy deployments:"
DEPLOY_DIRS=$(sudo find /opt/codedeploy-agent/deployment-root -maxdepth 1 -name "d-*" -type d 2>/dev/null | sort -r | head -3)
if [ -n "$DEPLOY_DIRS" ]; then
    echo "Recent deployment directories:"
    for dir in $DEPLOY_DIRS; do
        echo "Directory: $dir"
        echo "Created: $(sudo stat -c %y "$dir" 2>/dev/null)"
        if [ -d "$dir/deployment-archive" ]; then
            echo "Archive contents:"
            sudo ls -la "$dir/deployment-archive/" | head -10
        fi
        echo "---"
    done
else
    echo "No deployment directories found"
fi

echo ""
echo "5. Check if current deployment matches archive:"
LATEST_DEPLOY=$(sudo find /opt/codedeploy-agent/deployment-root -maxdepth 1 -name "d-*" -type d 2>/dev/null | sort -r | head -1)
if [ -n "$LATEST_DEPLOY" ] && [ -d "$LATEST_DEPLOY/deployment-archive" ]; then
    echo "Comparing latest deployment archive with /var/www:"
    echo "Archive has scripts directory:"
    sudo ls -la "$LATEST_DEPLOY/deployment-archive/scripts/" 2>/dev/null | head -5 || echo "No scripts in archive"
    echo "/var/www has scripts directory:"
    sudo ls -la "/var/www/scripts/" 2>/dev/null | head -5 || echo "No scripts in /var/www"
fi

echo ""
echo "=== Check completed ==="