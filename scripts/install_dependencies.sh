#!/bin/bash
set -e

echo "Installing system dependencies..."

# Update system
dnf update -y

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
    curl \
    awscli \
    jq

# Check if installation was successful
if ! php --version; then
    echo "ERROR: PHP installation failed"
    exit 1
fi

echo "PHP version installed:"
php --version

# Verify required PHP extensions
echo "Checking PHP extensions..."
php -m | grep -E "(mysqlnd|mbstring|xml|gd|zip|bcmath|intl|opcache)" || echo "Some PHP extensions may not be loaded"

# Check if composer will be needed
echo "Checking if Composer is available..."
which composer || echo "Composer not found, will be installed in next steps"

# Install additional useful packages for Amazon Linux 2023
echo "Installing additional packages..."
dnf install -y \
    mod_ssl \
    httpd-devel \
    htop \
    nano \
    vim || echo "Some additional packages failed to install, but continuing..."

# Install Composer
if [ ! -f /usr/local/bin/composer ]; then
    echo "Installing Composer..."
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    php -r "unlink('composer-setup.php');"
    chmod +x /usr/local/bin/composer
fi

# Enable and start MariaDB
systemctl enable mariadb
systemctl start mariadb || systemctl restart mariadb

# Enable and start Apache
systemctl enable httpd
systemctl start httpd || systemctl restart httpd

echo "Dependencies installation completed"
