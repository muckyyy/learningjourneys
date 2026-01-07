#!/bin/bash

# =============================================================================
# Laravel Deployment Script for Amazon Linux 2023 + Apache + PHP-FPM
# =============================================================================

set -e  # Exit on any error

# Variables
APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

echo "=== STARTING LARAVEL DEPLOYMENT ==="
echo "App Directory: $APP_DIR"
echo "Timestamp: $(date)"
echo ""

# Change to application directory
cd "$APP_DIR"
echo "Working directory: $(pwd)"

# Wait for deployment files to be ready
echo "Waiting for application files..."
WAIT_COUNT=0
while [ $WAIT_COUNT -lt 15 ] && [ ! -f "artisan" ]; do
    sleep 2
    WAIT_COUNT=$((WAIT_COUNT + 1))
done

if [ ! -f "artisan" ]; then
    echo "ERROR: Application files not ready"
    exit 1
fi
echo "✓ Application files ready"

# =============================================================================
# STEP 1: CREATE ENVIRONMENT FILE
# =============================================================================
echo "--- Setting up environment ---"

# Create .env from template
if [ ! -f ".env.example" ]; then
    echo "ERROR: .env.example file not found!"
    exit 1
fi

cp ".env.example" "$ENV_FILE"
echo "✓ .env file created"

# Get AWS secrets
SECRET_JSON=$(aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text)

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to fetch AWS secrets"
    exit 1
fi

# Parse and set secrets
DB_HOST=$(echo "$SECRET_JSON" | jq -r '.DB_HOST')
DB_USERNAME=$(echo "$SECRET_JSON" | jq -r '.DB_USERNAME')
DB_DATABASE=$(echo "$SECRET_JSON" | jq -r '.DB_DATABASE')
DB_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.DB_PASSWORD')
OPENAI_API_KEY=$(echo "$SECRET_JSON" | jq -r '.OPENAI_API_KEY')
APP_URL=$(echo "$SECRET_JSON" | jq -r '.APP_URL')
DB_CONNECTION=$(echo "$SECRET_JSON" | jq -r '.DB_CONNECTION')
APP_KEY=$(echo "$SECRET_JSON" | jq -r '.APP_KEY')
REVERB_APP_ID=$(echo "$SECRET_JSON" | jq -r '.REVERB_APP_ID')
REVERB_APP_KEY=$(echo "$SECRET_JSON" | jq -r '.REVERB_APP_KEY')
REVERB_APP_SECRET=$(echo "$SECRET_JSON" | jq -r '.REVERB_APP_SECRET')

# Environment variables for Reverb
REVERB_HOST_ENV=${REVERB_HOST:-"the-thinking-course.com"}
REVERB_PORT_ENV=${REVERB_PORT:-"443"}
REVERB_SCHEME_ENV=${REVERB_SCHEME:-"https"}
REVERB_SERVER_HOST_ENV=${REVERB_SERVER_HOST:-"0.0.0.0"}
REVERB_SERVER_PORT_ENV=${REVERB_SERVER_PORT:-"8080"}

# Environment variables for SMTP mail
MAIL_HOST=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_HOST')
MAIL_USERNAME=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_USERNAME')
MAIL_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_PASSWORD')
MAIL_FROM_ADDRESS=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_FROM')

echo "✓ Secrets loaded from AWS"

# =============================================================================
# STEP 2: UPDATE ENVIRONMENT FILE
# =============================================================================
echo "--- Updating environment variables ---"

# Use awk for safer variable replacement (handles special characters better than sed)
update_env_var() {
    local key="$1"
    local value="$2"
    local file="$3"
    awk -v key="$key" -v value="$value" '
        BEGIN { updated = 0 }
        $0 ~ "^" key "=" { 
            print key "=" value
            updated = 1
            next 
        }
        { print }
        END { 
            if (!updated) print key "=" value 
        }' "$file" > "$file.tmp" && mv "$file.tmp" "$file"
}

# Update all environment variables using the safer awk function
update_env_var "DB_HOST" "$DB_HOST" "$ENV_FILE"
update_env_var "DB_DATABASE" "$DB_DATABASE" "$ENV_FILE"
update_env_var "DB_USERNAME" "$DB_USERNAME" "$ENV_FILE"
update_env_var "DB_PASSWORD" "$DB_PASSWORD" "$ENV_FILE"
update_env_var "DB_CONNECTION" "$DB_CONNECTION" "$ENV_FILE"
update_env_var "APP_URL" "$APP_URL" "$ENV_FILE"
update_env_var "APP_KEY" "$APP_KEY" "$ENV_FILE"
update_env_var "OPENAI_API_KEY" "$OPENAI_API_KEY" "$ENV_FILE"

# Reverb configuration
update_env_var "REVERB_APP_ID" "$REVERB_APP_ID" "$ENV_FILE"
update_env_var "REVERB_APP_KEY" "$REVERB_APP_KEY" "$ENV_FILE"
update_env_var "REVERB_APP_SECRET" "$REVERB_APP_SECRET" "$ENV_FILE"
update_env_var "REVERB_HOST" "$REVERB_HOST_ENV" "$ENV_FILE"
update_env_var "REVERB_PORT" "$REVERB_PORT_ENV" "$ENV_FILE"
update_env_var "REVERB_SCHEME" "$REVERB_SCHEME_ENV" "$ENV_FILE"
update_env_var "REVERB_SERVER_HOST" "$REVERB_SERVER_HOST_ENV" "$ENV_FILE"
update_env_var "REVERB_SERVER_PORT" "$REVERB_SERVER_PORT_ENV" "$ENV_FILE"

# FIX: Override Reverb settings for production server-side broadcasting
# Laravel needs to connect to Reverb via internal HTTP, not external HTTPS
update_env_var "REVERB_HOST" "127.0.0.1" "$ENV_FILE"
update_env_var "REVERB_PORT" "8080" "$ENV_FILE"
update_env_var "REVERB_SCHEME" "http" "$ENV_FILE"
# Update Google Auth credentials
update_env_var "GOOGLE_CLIENT_ID" "$GOOGLE_CLIENT_ID" "$ENV_FILE"
update_env_var "GOOGLE_CLIENT_SECRET" "$GOOGLE_CLIENT_SECRET" "$ENV_FILE"
update_env_var "GOOGLE_REDIRECT_URI" "${APP_URL}/auth/google/callback" "$ENV_FILE"
update_env_var "GOOGLE_OAUTH_ENABLED" "$GOOGLE_OAUTH_ENABLED" "$ENV_FILE"

echo "✓ Environment configured with production Reverb settings" 

# =============================================================================
# STEP 2.5: FORCE DEPLOY CORRECTED APACHE CONFIGURATION
# =============================================================================
echo "--- Force deploying corrected Apache streaming configuration ---"

# Remove any existing broken configurations first
sudo rm -f /etc/httpd/conf.d/phpfpm-streaming.conf
sudo rm -f /etc/httpd/conf.d/keepalive-streaming.conf

# Deploy our corrected phpfpm-streaming.conf from repository  
PHPFPM_CONF="/etc/httpd/conf.d/phpfpm-streaming.conf"
SOURCE_PHPFPM_CONF="$APP_DIR/config/apache/phpfpm-streaming.conf"

if [ -f "$SOURCE_PHPFPM_CONF" ]; then
    echo "Force deploying corrected phpfpm-streaming.conf from repository..."
    cp "$SOURCE_PHPFPM_CONF" "$PHPFPM_CONF"
    chmod 644 "$PHPFPM_CONF"
    echo "✓ Corrected phpfpm-streaming.conf deployed from repository"
    echo "Configuration preview:"
    head -20 "$PHPFPM_CONF"
else
    echo "⚠ Source phpfpm-streaming.conf not found at: $SOURCE_PHPFPM_CONF"
    echo "Available files in config/apache/:"
    ls -la "$APP_DIR/config/apache/" || echo "Directory not found"
fi

echo "✓ Apache streaming configuration force deployment completed"

# =============================================================================
# STEP 3: APACHE PROXY MODULES CONFIGURATION
# =============================================================================
echo "--- Configuring Apache proxy modules ---"

PROXY_CONF="/etc/httpd/conf.modules.d/00-proxy.conf"

# Create proxy modules configuration if missing
if [ ! -f "$PROXY_CONF" ]; then
    cat > "$PROXY_CONF" << 'EOF'
# Proxy modules for Laravel and WebSocket support
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
EOF
    echo "✓ Proxy modules configured"
else
    echo "✓ Proxy modules already configured"
fi

echo "✓ Apache proxy modules configuration completed"

# =============================================================================
# STEP 4: DEPLOY APACHE CONFIGURATION
# =============================================================================
echo "--- Deploying Apache configuration ---"

SOURCE_CONF="$APP_DIR/config/apache/learningjourneys.conf"
TARGET_CONF="/etc/httpd/conf.d/learningjourneys.conf"

if [ -f "$SOURCE_CONF" ]; then
    # Update domain in configuration
    DOMAIN=$(echo "${APP_URL}" | sed -E 's|^https?://||' | sed 's|/.*$||')
    sed "s/ServerName .*/ServerName ${DOMAIN}/" "$SOURCE_CONF" > "$TARGET_CONF"
    sed -i "s/ServerAlias .*/ServerAlias www.${DOMAIN}/" "$TARGET_CONF"
    echo "✓ Apache VirtualHost deployed for $DOMAIN"
else
    echo "⚠ Apache configuration not found at: $SOURCE_CONF"
fi

# =============================================================================
# STEP 5: SET PERMISSIONS
# =============================================================================
echo "--- Setting permissions ---"

chown -R ec2-user:apache "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Ensure log directories exist
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/framework/cache"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"

chown -R ec2-user:apache "$APP_DIR/storage"
chown -R ec2-user:apache "$APP_DIR/bootstrap/cache"

echo "✓ Permissions set"

# =============================================================================
# STEP 6: LARAVEL OPTIMIZATION
# =============================================================================
echo "--- Laravel optimization ---"

cd "$APP_DIR"

# Clear and cache configuration
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

echo "✓ Laravel optimized"

# =============================================================================
# STEP 7: RESTART SERVICES
# =============================================================================
echo "--- Restarting services ---"

# Restart PHP-FPM
systemctl restart php-fpm
echo "✓ PHP-FPM restarted"

# Restart Apache
systemctl restart httpd
echo "✓ Apache restarted"

# =============================================================================
# STEP 7.5: DEPLOY AND START LARAVEL REVERB SERVICE
# =============================================================================
echo "--- Setting up Laravel Reverb service ---"

# Deploy Reverb systemd service
REVERB_SERVICE_SOURCE="$APP_DIR/config/systemd/laravel-reverb.service"
REVERB_SERVICE_TARGET="/etc/systemd/system/laravel-reverb.service"

if [ -f "$REVERB_SERVICE_SOURCE" ]; then
    cp "$REVERB_SERVICE_SOURCE" "$REVERB_SERVICE_TARGET"
    chmod 644 "$REVERB_SERVICE_TARGET"
    
    # Reload systemd and start Reverb service
    systemctl daemon-reload
    systemctl enable laravel-reverb.service
    systemctl restart laravel-reverb.service
    
    # Wait a moment for service to start
    sleep 3
    
    if systemctl is-active --quiet laravel-reverb.service; then
        echo "✓ Laravel Reverb service is running"
    else
        echo "⚠ Laravel Reverb service failed to start"
        systemctl status laravel-reverb.service --no-pager
    fi
else
    echo "⚠ Reverb service file not found at: $REVERB_SERVICE_SOURCE"
fi

echo "✓ Laravel Reverb service setup completed"

# Verify Apache is running
if systemctl is-active --quiet httpd; then
    echo "✓ Apache is running"
else
    echo "✗ Apache failed to start"
    systemctl status httpd --no-pager
    exit 1
fi

# =============================================================================
# STEP 8: FINAL VERIFICATION
# =============================================================================
echo "--- Final verification ---"

# Check if proxy modules are loaded
if httpd -M 2>/dev/null | grep -q "proxy_module"; then
    echo "✓ Proxy modules loaded"
else
    echo "⚠ Proxy modules not detected"
fi

# Check PHP-FPM
if systemctl is-active --quiet php-fpm; then
    echo "✓ PHP-FPM is running"
else
    echo "⚠ PHP-FPM is not running"
fi

echo ""
echo "=== DEPLOYMENT COMPLETED SUCCESSFULLY ==="
echo "Application URL: $APP_URL"
echo "Timestamp: $(date)"