#!/bin/bash

echo "=== Manual Environment Configuration Test ==="

# Change to application directory
cd /var/www || { echo "Failed to change to /var/www"; exit 1; }

echo "Current directory: $(pwd)"
echo "Files in current directory:"
ls -la

echo ""
echo "1. Running configure_environment.sh manually..."
if [ -f "configure_environment.sh" ]; then
    chmod +x configure_environment.sh
    bash -x configure_environment.sh
    echo "configure_environment.sh completed with exit code: $?"
else
    echo "configure_environment.sh not found"
fi

echo ""
echo "2. Running setup_database.sh manually..."
if [ -f "setup_database.sh" ]; then
    chmod +x setup_database.sh
    bash -x setup_database.sh
    echo "setup_database.sh completed with exit code: $?"
else
    echo "setup_database.sh not found"
fi

echo ""
echo "3. Running set_permissions.sh manually..."
if [ -f "set_permissions.sh" ]; then
    chmod +x set_permissions.sh
    bash -x set_permissions.sh
    echo "set_permissions.sh completed with exit code: $?"
else
    echo "set_permissions.sh not found"
fi

echo ""
echo "4. Checking results..."
if [ -f ".env" ]; then
    echo "✓ .env file exists"
    echo "File permissions: $(ls -l .env)"
    echo "File size: $(wc -l < .env) lines"
    echo "First 10 lines:"
    head -10 .env
    echo ""
    echo "Checking for key variables:"
    grep -E "^(APP_KEY|DB_PASSWORD|OPENAI_API_KEY)" .env | head -3 || echo "Key variables not found"
else
    echo "✗ .env file not created"
fi

echo ""
echo "5. Testing Laravel..."
if [ -f "artisan" ]; then
    echo "✓ Laravel artisan available"
    echo "Testing Laravel environment:"
    php artisan env 2>/dev/null || echo "Cannot determine Laravel environment"
else
    echo "✗ Laravel artisan not found"
fi

echo ""
echo "=== Manual test completed ==="