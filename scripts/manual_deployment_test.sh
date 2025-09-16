sudo systemctl restart codedeploy-agent#!/bin/bash

echo "=== Manual Environment Configuration Test ==="

# Change to application directory
cd /var/www || { echo "Failed to change to /var/www"; exit 1; }

echo "Current directory: $(pwd)"
echo "Files in current directory:"
ls -la

echo ""
echo "1. Running install_dependencies.sh manually..."
if [ -f "scripts/install_dependencies.sh" ]; then
    chmod +x scripts/install_dependencies.sh
    bash -x scripts/install_dependencies.sh
    echo "install_dependencies.sh completed with exit code: $?"
else
    echo "install_dependencies.sh not found"
fi

echo ""
echo "2. Running configure_environment.sh manually..."
if [ -f "scripts/configure_environment.sh" ]; then
    chmod +x scripts/configure_environment.sh
    bash -x scripts/configure_environment.sh
    echo "configure_environment.sh completed with exit code: $?"
else
    echo "configure_environment.sh not found"
fi

echo ""
echo "3. Checking results..."
if [ -f ".env" ]; then
    echo "✓ .env file created"
    echo "File permissions: $(ls -l .env)"
    echo "First 10 lines:"
    head -10 .env
else
    echo "✗ .env file not created"
fi

echo ""
echo "=== Manual test completed ==="