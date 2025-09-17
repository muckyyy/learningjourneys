#!/bin/bash
set -e

echo "=== CODEDEPLOY ARTIFACT INSPECTION ==="
echo "Checking what CodeDeploy received before installation..."
echo "Current working directory: $(pwd)"
echo ""

echo "CodeDeploy workspace directory:"
if [ -d "/opt/codedeploy-agent/deployment-root" ]; then
    DEPLOYMENT_ROOT=$(find /opt/codedeploy-agent/deployment-root -name "*" -type d | head -5)
    echo "Deployment root contents:"
    echo "$DEPLOYMENT_ROOT"
    
    # Find the most recent deployment directory
    LATEST_DEPLOYMENT=$(find /opt/codedeploy-agent/deployment-root -name "*" -type d -exec ls -dt {} + | head -1)
    if [ -n "$LATEST_DEPLOYMENT" ]; then
        echo ""
        echo "Latest deployment directory: $LATEST_DEPLOYMENT"
        echo "Contents:"
        ls -la "$LATEST_DEPLOYMENT"/ || echo "Cannot list deployment directory"
        
        # Look for the source files
        if [ -d "$LATEST_DEPLOYMENT/deployment-archive" ]; then
            echo ""
            echo "Source archive contents:"
            ls -la "$LATEST_DEPLOYMENT/deployment-archive/"
            echo ""
            echo "Checking for critical files in archive:"
            echo "- artisan: $([ -f "$LATEST_DEPLOYMENT/deployment-archive/artisan" ] && echo "✓ Present" || echo "✗ Missing")"
            echo "- app directory: $([ -d "$LATEST_DEPLOYMENT/deployment-archive/app" ] && echo "✓ Present" || echo "✗ Missing")"
            echo "- scripts directory: $([ -d "$LATEST_DEPLOYMENT/deployment-archive/scripts" ] && echo "✓ Present" || echo "✗ Missing")"
            echo "- config directory: $([ -d "$LATEST_DEPLOYMENT/deployment-archive/config" ] && echo "✓ Present" || echo "✗ Missing")"
        fi
    fi
else
    echo "CodeDeploy deployment root not found"
fi

echo ""
echo "=== BEFORE INSTALL PHASE ==="
echo "Time: $(date)"

# ============================================================================
# STEP 0: CLEANUP EXISTING DEPLOYMENT FILES
# ============================================================================
echo ""
echo "--- Cleaning up existing deployment files ---"

# Ensure we can overwrite any existing files by fixing permissions and ownership
echo "Preparing /var/www for deployment..."

# Stop any services that might be locking files
systemctl stop httpd >/dev/null 2>&1 || true
systemctl stop laravel-websockets >/dev/null 2>&1 || true
systemctl stop websocket-server >/dev/null 2>&1 || true
echo "Waiting for services to fully stop..."
sleep 5  # Give services extra time to stop and release file locks

# Create /var/www if it doesn't exist
mkdir -p /var/www

# Show what's currently in /var/www
echo "Current contents of /var/www:"
ls -la /var/www/ 2>/dev/null || echo "Directory is empty or doesn't exist"

# Remove application files but keep any system files
echo "Removing existing application files from /var/www (ensuring fresh deployment)..."
rm -rf /var/www/app 2>/dev/null || true
rm -rf /var/www/bootstrap 2>/dev/null || true
rm -rf /var/www/config 2>/dev/null || true
rm -rf /var/www/database 2>/dev/null || true
rm -rf /var/www/public 2>/dev/null || true
rm -rf /var/www/resources 2>/dev/null || true
rm -rf /var/www/routes 2>/dev/null || true
rm -rf /var/www/storage 2>/dev/null || true
rm -rf /var/www/tests 2>/dev/null || true
rm -rf /var/www/vendor 2>/dev/null || true
rm -rf /var/www/node_modules 2>/dev/null || true
echo "Removing any existing environment files to ensure fresh configuration..."
# Make files writable and remove them forcefully
chmod -R 777 /var/www 2>/dev/null || true
rm -f /var/www/.env* 2>/dev/null || true
# Double-check .env removal with more aggressive approach
if [ -f /var/www/.env ]; then
    echo "WARNING: .env file still exists, trying chmod and forced removal..."
    chmod 666 /var/www/.env 2>/dev/null || true
    rm -f /var/www/.env 2>/dev/null || true
    # If still exists, move it out of the way
    if [ -f /var/www/.env ]; then
        echo "Moving stubborn .env file to backup location..."
        mv /var/www/.env /tmp/.env.backup.$(date +%s) 2>/dev/null || true
    fi
fi
rm -f /var/www/composer.* 2>/dev/null || true
rm -f /var/www/package*.json 2>/dev/null || true
rm -f /var/www/webpack.* 2>/dev/null || true
rm -f /var/www/artisan 2>/dev/null || true
rm -f /var/www/*.php 2>/dev/null || true
rm -f /var/www/*.md 2>/dev/null || true

# Note: We intentionally do NOT remove /var/www/scripts as CodeDeploy needs it for hooks

# Set proper ownership and permissions for the directory
chown -R root:root /var/www 2>/dev/null || true
chmod 755 /var/www

echo "✓ /var/www cleaned (keeping system files) and ready for deployment"
echo "Contents after cleanup:"
ls -la /var/www/ 2>/dev/null || echo "Directory cleaned"

echo "✓ Cleanup completed"

# ============================================================================
# STEP 1: STOP SERVICES
# ============================================================================
echo ""
echo "--- Stopping services ---"

# Stop Laravel WebSocket service if running
if systemctl is-active --quiet laravel-websockets 2>/dev/null; then
    echo "Stopping Laravel WebSockets service..."
    systemctl stop laravel-websockets
fi

# Stop Apache if running (optional - for zero downtime we might skip this)
if systemctl is-active --quiet httpd; then
    echo "Stopping Apache..."
    systemctl stop httpd
fi

echo "✓ Services stopped"

# ============================================================================
# STEP 2: INSTALL SYSTEM DEPENDENCIES
# ============================================================================
echo ""
echo "--- Installing system dependencies ---"

# Update system (skip if recently updated)
echo "Checking system updates..."
dnf check-update --quiet || echo "System packages checked"

# Install required packages
echo "Installing core packages..."
dnf install -y \
    httpd \
    php8.4 \
    php8.4-cli \
    php8.4-fpm \
    php8.4-mysqlnd \
    php8.4-opcache \
    php8.4-xml \
    php8.4-mbstring \
    php8.4-gd \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-intl \
    php8.4-process \
    php8.4-common \
    nodejs \
    npm \
    git \
    unzip \
    wget \
    awscli \
    jq \
    php-tokenizer

# Check if installation was successful
echo "Verifying PHP installation..."
if ! php --version; then
    echo "ERROR: PHP installation failed"
    exit 1
fi

# Quick PHP version check
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null || echo "unknown")
echo "PHP version: $PHP_VERSION"

# Quick verification of essential components
echo "Quick system verification..."

# Verify PHP extensions (simplified)
echo "Essential PHP extensions check..."
if php -m | grep -q "mysqli\|mysqlnd"; then
    echo "✓ MySQL PHP extension available"
else
    echo "⚠ MySQL PHP extension missing"
fi

# Check if composer will be needed
if which composer >/dev/null 2>&1; then
    echo "✓ Composer available"
else
    echo "→ Composer will be installed"
fi

# Verify curl is available
if curl --version >/dev/null 2>&1; then
    echo "✓ Curl available"
else
    echo "⚠ Curl not available"
fi

# Install additional useful packages for Amazon Linux 2023
echo "Installing additional packages..."
if ! dnf install -y mod_ssl; then
    echo "⚠ mod_ssl installation failed, continuing..."
fi
echo "Skipping optional packages to save time"

# Install Composer
if [ ! -f /usr/local/bin/composer ]; then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" || {
        echo "ERROR: Failed to download composer installer"
        exit 1
    }
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer || {
        echo "ERROR: Failed to install composer"
        exit 1
    }
    php -r "unlink('composer-setup.php');" || true
    chmod +x /usr/local/bin/composer
    echo "✓ Composer installed successfully"
else
    echo "✓ Composer is already installed"
fi

# Enable Apache (but don't start it yet - wait for after_install)
echo "Enabling Apache service..."
systemctl enable httpd >/dev/null 2>&1 || true
echo "✓ Apache service enabled (will start after application files are deployed)"

echo "✓ Dependencies installation completed successfully!"

echo ""
echo "=== BEFORE INSTALL PHASE COMPLETED ==="
echo "Final state of /var/www before file copy:"
ls -la /var/www/ 2>/dev/null || echo "Directory not accessible"

# Ensure script exits with success code
exit 0