#!/bin/bash
set -e

echo "=== BEFORE INSTALL PHASE ==="
echo "Time: $(date)"

# ============================================================================
# STEP 0: CLEANUP EXISTING DEPLOYMENT FILES
# ============================================================================
echo ""
echo "--- Cleaning up existing deployment files ---"

# Ensure we can overwrite any existing files by fixing permissions and ownership
if [ -d "/var/www" ]; then
    echo "Found existing /var/www directory, preparing for overwrite..."
    
    # Fix ownership of existing files to allow overwrite
    chown -R root:root /var/www 2>/dev/null || echo "Warning: Could not change ownership to root"
    
    # Fix permissions to allow overwrite
    chmod -R 777 /var/www 2>/dev/null || echo "Warning: Could not change permissions"
    
    # Remove any problematic files that might cause conflicts
    rm -f /var/www/.env 2>/dev/null || true
    rm -f /var/www/.env.backup 2>/dev/null || true
    rm -f /var/www/.env.production 2>/dev/null || true
    
    # Remove any lock files or temporary files that might interfere
    find /var/www -name "*.lock" -delete 2>/dev/null || true
    find /var/www -name ".DS_Store" -delete 2>/dev/null || true
    
    echo "✓ Prepared /var/www for file overwrite"
else
    echo "Creating /var/www directory..."
    mkdir -p /var/www
    echo "✓ /var/www directory created"
fi

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
    mariadb105-server \
    mariadb105 \
    php \
    php-cli \
    php-fpm \
    php-mysqlnd \
    php-opcache \
    php-xml \
    php-mbstring \
    php-gd \
    php-zip \
    php-bcmath \
    php-intl \
    php-process \
    php-common \
    nodejs \
    npm \
    git \
    unzip \
    wget \
    awscli \
    jq

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

# Enable and start MariaDB
echo "Starting MariaDB..."
systemctl enable mariadb >/dev/null 2>&1 || true
if systemctl is-active --quiet mariadb; then
    echo "✓ MariaDB is already running"
else
    systemctl start mariadb >/dev/null 2>&1 && echo "✓ MariaDB started successfully" || echo "⚠ MariaDB start failed, will retry in after_install"
fi

# Enable and start Apache
echo "Starting Apache..."
systemctl enable httpd >/dev/null 2>&1 || true
if systemctl is-active --quiet httpd; then
    echo "✓ Apache is already running"
else
    systemctl start httpd >/dev/null 2>&1 && echo "✓ Apache started successfully" || echo "⚠ Apache start failed, will retry in after_install"
fi

echo "✓ Dependencies installation completed successfully!"

echo ""
echo "=== BEFORE INSTALL PHASE COMPLETED ==="

# Ensure script exits with success code
exit 0