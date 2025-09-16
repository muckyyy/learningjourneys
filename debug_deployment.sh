#!/bin/bash
set -e

echo "=== Manual Deployment Debug Script ==="
echo "Time: $(date)"
echo "User: $(whoami)"
echo "Working directory: $(pwd)"
echo ""

# Check if we're in the right location
echo "=== Checking deployment location ==="
ls -la /var/www/ | head -10
echo ""

# Check if our deployment scripts exist
echo "=== Checking deployment scripts ==="
if [ -f "/var/www/configure_environment.sh" ]; then
    echo "✓ configure_environment.sh exists"
    ls -la /var/www/configure_environment.sh
else
    echo "✗ configure_environment.sh missing"
fi

if [ -f "/var/www/setup_database.sh" ]; then
    echo "✓ setup_database.sh exists" 
    ls -la /var/www/setup_database.sh
else
    echo "✗ setup_database.sh missing"
fi

if [ -f "/var/www/set_permissions.sh" ]; then
    echo "✓ set_permissions.sh exists"
    ls -la /var/www/set_permissions.sh
else
    echo "✗ set_permissions.sh missing"
fi

echo ""

# Check AWS credentials
echo "=== Checking AWS credentials ==="
aws sts get-caller-identity || echo "AWS credentials not working"
echo ""

# Check if we can access the application directory
echo "=== Checking Laravel application ==="
cd /var/www
if [ -f "artisan" ]; then
    echo "✓ Laravel artisan file found"
    php --version
else
    echo "✗ Laravel artisan file not found"
fi

# Try to run configure_environment.sh manually
echo ""
echo "=== Attempting to run configure_environment.sh manually ==="
if [ -f "/var/www/configure_environment.sh" ]; then
    echo "Running configure_environment.sh with verbose output..."
    bash -x /var/www/configure_environment.sh 2>&1 || echo "configure_environment.sh failed"
else
    echo "configure_environment.sh not found"
fi