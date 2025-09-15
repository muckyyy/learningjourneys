#!/bin/bash
set -e

echo "Installing system dependencies..."

# Update system
dnf update -y

# Install required packages
dnf install -y \
    httpd \
    mariadb105-server \
    mariadb105 \
    php8.2 \
    php8.2-cli \
    php8.2-fpm \
    php8.2-mysqlnd \
    php8.2-opcache \
    php8.2-xml \
    php8.2-mbstring \
    php8.2-gd \
    php8.2-curl \
    php8.2-zip \
    php8.2-bcmath \
    php8.2-intl \
    php8.2-process \
    php8.2-common \
    nodejs \
    npm \
    git \
    unzip \
    wget \
    curl \
    awscli2 \
    jq

# Install additional useful packages for Amazon Linux 2023
dnf install -y \
    mod_ssl \
    mod_rewrite \
    htop \
    nano

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
