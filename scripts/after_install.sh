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
REVERB_HOST_ENV=${REVERB_HOST:-"thethinkingcourse.com"}
REVERB_PORT_ENV=${REVERB_PORT:-"443"}
REVERB_SCHEME_ENV=${REVERB_SCHEME:-"https"}
REVERB_SERVER_HOST_ENV=${REVERB_SERVER_HOST:-"0.0.0.0"}
REVERB_SERVER_PORT_ENV=${REVERB_SERVER_PORT:-"8080"}

# Environment variables for SMTP mail
MAIL_HOST=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_HOST')
MAIL_USERNAME=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_USERNAME')
MAIL_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_PASSWORD')
MAIL_FROM_ADDRESS=$(echo "$SECRET_JSON" | jq -r '.SMTP_MAIL_FROM')

# Google OAuth credentials
GOOGLE_CLIENT_ID=$(echo "$SECRET_JSON" | jq -r '.GOOGLE_CLIENT_ID')
GOOGLE_CLIENT_SECRET=$(echo "$SECRET_JSON" | jq -r '.GOOGLE_CLIENT_SECRET')


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

# Production: use database queue so AI jobs run in background workers, not PHP-FPM
update_env_var "QUEUE_CONNECTION" "database" "$ENV_FILE"

echo "✓ Environment configured with production Reverb settings"
echo "✓ QUEUE_CONNECTION set to database for async AI processing"

# =============================================================================
# STEP 2.5: DEPLOY PHP-FPM POOL CONFIGURATIONS
# =============================================================================
echo "--- Deploying PHP-FPM pool configurations ---"

# Ensure log directory exists
mkdir -p /var/log/php-fpm
chown apache:apache /var/log/php-fpm

# Find the PHP-FPM pool config directory
FPM_POOL_DIR=""
for candidate in /etc/php-fpm.d /etc/php/8.4/fpm/pool.d /etc/php/8.2/fpm/pool.d; do
    if [ -d "$candidate" ]; then
        FPM_POOL_DIR="$candidate"
        break
    fi
done

if [ -z "$FPM_POOL_DIR" ]; then
    echo "⚠ PHP-FPM pool directory not found, trying to create /etc/php-fpm.d"
    mkdir -p /etc/php-fpm.d
    FPM_POOL_DIR="/etc/php-fpm.d"
fi

echo "PHP-FPM pool directory: $FPM_POOL_DIR"

# Deploy tuned www pool (replaces distro default)
if [ -f "$APP_DIR/config/php-fpm/www.conf" ]; then
    cp "$APP_DIR/config/php-fpm/www.conf" "$FPM_POOL_DIR/www.conf"
    chmod 644 "$FPM_POOL_DIR/www.conf"
    echo "✓ www pool config deployed (ondemand, max_children=8, terminate_timeout=60s)"
else
    echo "⚠ www pool config not found in repo"
fi

# Deploy streaming pool
if [ -f "$APP_DIR/config/php-fpm/www-streaming.conf" ]; then
    cp "$APP_DIR/config/php-fpm/www-streaming.conf" "$FPM_POOL_DIR/www-streaming.conf"
    chmod 644 "$FPM_POOL_DIR/www-streaming.conf"
    echo "✓ www-streaming pool config deployed (ondemand, max_children=3, terminate_timeout=180s)"
else
    echo "⚠ www-streaming pool config not found in repo"
fi

echo "✓ PHP-FPM pool configurations deployed"

# =============================================================================
# STEP 2.6: FORCE DEPLOY CORRECTED APACHE CONFIGURATION
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

# Set ownership to apache:apache (the user running Apache/PHP-FPM)
# This prevents permission denied errors when Apache tries to write cache files
chown -R apache:apache "$APP_DIR"

# Set directory and file permissions
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;

# Storage and bootstrap/cache directories need write permissions for Apache
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Ensure all required directories exist with correct permissions
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/framework/cache"
mkdir -p "$APP_DIR/storage/framework/cache/data"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/app/public"

# Ensure all storage directories are owned by apache:apache
chown -R apache:apache "$APP_DIR/storage"
chown -R apache:apache "$APP_DIR/bootstrap/cache"

# Double-check permissions on critical cache directories
chmod -R 775 "$APP_DIR/storage/framework/cache"
chmod -R 775 "$APP_DIR/storage/framework/sessions"
chmod -R 775 "$APP_DIR/storage/framework/views"

echo "✓ Permissions set (ownership: apache:apache, writable: storage/ bootstrap/cache/)"

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
php artisan event:clear
php artisan event:cache

echo "✓ Laravel optimized"

# =============================================================================
# STEP 6.5: PUBLISH VENDOR ASSETS & CREATE SYMLINKS
# =============================================================================
echo "--- Publishing vendor assets ---"

cd "$APP_DIR"

# Create storage symlink (safe to re-run; recreates if missing)
php artisan storage:link 2>/dev/null || true
echo "✓ Storage symlink ensured"

# Publish Laravel framework assets (pagination views, error pages, etc.)
php artisan vendor:publish --tag=laravel-assets --ansi --force
echo "✓ Laravel framework assets published"

# Publish Log Viewer frontend assets (prevents RuntimeException on stale assets)
php artisan vendor:publish --tag=log-viewer-assets --force
echo "✓ Log Viewer assets published"

# Publish Request Analytics dashboard assets
php artisan vendor:publish --tag=laravel-request-analytics-assets --force
echo "✓ Request Analytics assets published"

# Reset permission cache in case roles/permissions were updated
php artisan permission:cache-reset 2>/dev/null || true
echo "✓ Permission cache reset"

# Seed legal documents if the tables are empty
php artisan db:seed --class=LegalDocumentSeeder --force 2>/dev/null || true
echo "✓ Legal documents seeded"

# Prune old Telescope entries to keep the table manageable
php artisan telescope:prune --hours=72 2>/dev/null || true
echo "✓ Telescope entries pruned"

# Prune old Pulse data beyond retention period and take initial server snapshot
php artisan pulse:clear --type=aggregates --force 2>/dev/null || true
php artisan pulse:restart 2>/dev/null || true
echo "✓ Pulse data maintained"

echo "✓ All vendor assets published successfully"

# =============================================================================
# STEP 6.9: INSTALL AND CONFIGURE FAIL2BAN
# =============================================================================
echo "--- Setting up Fail2Ban bot/scanner protection ---"

# Install fail2ban if not already installed
if ! command -v fail2ban-client &> /dev/null; then
    echo "Installing fail2ban..."
    # Amazon Linux 2023 uses dnf; fall back to yum
    if command -v dnf &> /dev/null; then
        dnf install -y fail2ban fail2ban-firewalld || yum install -y fail2ban
    else
        yum install -y fail2ban
    fi
    echo "✓ Fail2ban installed"
else
    echo "✓ Fail2ban already installed"
fi

# Deploy jail configuration
FAIL2BAN_SOURCE="$APP_DIR/config/fail2ban"
if [ -d "$FAIL2BAN_SOURCE" ]; then
    # Deploy jail.local
    if [ -f "$FAIL2BAN_SOURCE/jail.local" ]; then
        cp "$FAIL2BAN_SOURCE/jail.local" /etc/fail2ban/jail.local
        chmod 644 /etc/fail2ban/jail.local
        echo "✓ Fail2ban jail.local deployed"
    fi

    # Deploy custom filter definitions
    if [ -d "$FAIL2BAN_SOURCE/filter.d" ]; then
        cp "$FAIL2BAN_SOURCE/filter.d/"*.conf /etc/fail2ban/filter.d/
        chmod 644 /etc/fail2ban/filter.d/apache-*.conf
        echo "✓ Fail2ban custom filters deployed:"
        ls -1 /etc/fail2ban/filter.d/apache-*.conf 2>/dev/null | while read f; do echo "  - $(basename $f)"; done
    fi
else
    echo "⚠ Fail2ban config directory not found at: $FAIL2BAN_SOURCE"
fi

# Ensure firewalld is running (required for fail2ban's firewallcmd-ipset action)
if command -v firewall-cmd &> /dev/null; then
    systemctl enable firewalld 2>/dev/null || true
    systemctl start firewalld 2>/dev/null || true
    # Ensure HTTP/HTTPS are allowed through the firewall
    firewall-cmd --permanent --add-service=http 2>/dev/null || true
    firewall-cmd --permanent --add-service=https 2>/dev/null || true
    firewall-cmd --reload 2>/dev/null || true
    echo "✓ Firewalld configured"
fi

# Enable and start fail2ban
systemctl enable fail2ban
systemctl restart fail2ban

# Wait for fail2ban to initialise and verify
sleep 3
if systemctl is-active --quiet fail2ban; then
    echo "✓ Fail2ban is running"
    # Show active jails
    fail2ban-client status 2>/dev/null | grep "Jail list" || true
else
    echo "⚠ Fail2ban failed to start"
    systemctl status fail2ban --no-pager | tail -10
fi

echo "✓ Fail2ban bot/scanner protection setup completed"

# Ensure runtime ownership before any service restart/health checks.
# If deployment aborts later (e.g. Apache restart failure), app permissions remain correct.
echo "--- Early runtime permission fix (pre-service restart) ---"
chown -R apache:apache "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"
echo "✓ Early runtime permissions applied"

# =============================================================================
# STEP 7: RESTART SERVICES
# =============================================================================
echo "--- Restarting services ---"

# Restart PHP-FPM
systemctl restart php-fpm
echo "✓ PHP-FPM restarted"

# Validate Apache config before restart so we fail with actionable diagnostics.
if ! httpd -t; then
    echo "✗ Apache configuration test failed"
    journalctl -xeu httpd.service --no-pager | tail -60
    exit 1
fi

# Restart Apache
if ! systemctl restart httpd; then
    echo "✗ Apache restart failed"
    systemctl status httpd --no-pager | tail -60
    journalctl -xeu httpd.service --no-pager | tail -60
    exit 1
fi
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

# =============================================================================
# STEP 7.6: DEPLOY AND START LARAVEL QUEUE WORKER SERVICE
# =============================================================================
echo "--- Setting up Laravel Queue Worker service ---"

# Run migration to ensure jobs table exists
echo "Running queue table migration..."
cd "$APP_DIR"
php artisan migrate --force 2>/dev/null || echo "⚠ Migration skipped (may already be up to date)"

QUEUE_WORKERS=8
QUEUE_SERVICE_SOURCE="$APP_DIR/config/systemd/laravel-queue@.service"
QUEUE_SERVICE_TARGET="/etc/systemd/system/laravel-queue@.service"

# Remove old non-template service if it exists
systemctl stop laravel-queue.service 2>/dev/null || true
systemctl disable laravel-queue.service 2>/dev/null || true
rm -f /etc/systemd/system/laravel-queue.service 2>/dev/null || true

if [ -f "$QUEUE_SERVICE_SOURCE" ]; then
    cp "$QUEUE_SERVICE_SOURCE" "$QUEUE_SERVICE_TARGET"
    chmod 644 "$QUEUE_SERVICE_TARGET"
    systemctl daemon-reload

    RUNNING=0
    for i in $(seq 1 $QUEUE_WORKERS); do
        systemctl enable laravel-queue@${i}.service
        systemctl restart laravel-queue@${i}.service
    done

    sleep 3

    for i in $(seq 1 $QUEUE_WORKERS); do
        if systemctl is-active --quiet laravel-queue@${i}.service; then
            RUNNING=$((RUNNING + 1))
        fi
    done

    echo "✓ Laravel Queue Workers running: $RUNNING / $QUEUE_WORKERS"
else
    echo "⚠ Queue worker service file not found at: $QUEUE_SERVICE_SOURCE"
fi

echo "✓ Laravel Queue Worker service setup completed"

# Verify Apache is running
if systemctl is-active --quiet httpd; then
    echo "✓ Apache is running"
else
    echo "✗ Apache failed to start"
    systemctl status httpd --no-pager
    exit 1
fi

# =============================================================================
# STEP 7.9: FINAL PERMISSION FIX
# =============================================================================
# CRITICAL: All previous steps (artisan config:cache, route:cache, view:cache,
# vendor:publish, storage:link, migrate, etc.) ran as root so any files they
# created are owned by root. We must re-apply ownership AFTER every artisan
# command has finished. This is the single authoritative permission pass.
echo "--- Final permission fix (post-artisan) ---"

# Ownership: everything under APP_DIR must be apache:apache
chown -R apache:apache "$APP_DIR"

# Base permissions: 644 for files, 755 for directories
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;

# Writable directories that Apache/PHP-FPM need to write to at runtime
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Ensure all framework cache/session/view dirs exist and are writable
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/framework/cache/data"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/app/public"

chown -R apache:apache "$APP_DIR/storage"
chown -R apache:apache "$APP_DIR/bootstrap/cache"

# Make artisan executable
chmod 755 "$APP_DIR/artisan"

# Protect .env but keep it readable by apache
chmod 640 "$APP_DIR/.env"
chown apache:apache "$APP_DIR/.env"

# Verify no root-owned files remain in storage or resources
ROOT_FILES=$(find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/resources" -user root 2>/dev/null | head -20)
if [ -n "$ROOT_FILES" ]; then
    echo "⚠ Found root-owned files (fixing now):"
    echo "$ROOT_FILES"
    chown -R apache:apache "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/resources"
else
    echo "✓ No root-owned files in storage, bootstrap/cache, or resources"
fi

echo "✓ Final permissions applied (all files owned by apache:apache)"

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

# Check Fail2ban
if systemctl is-active --quiet fail2ban; then
    echo "✓ Fail2ban is running"
    BANNED=$(fail2ban-client status 2>/dev/null | grep "Jail list" || echo "  unknown")
    echo "  Active jails: $BANNED"
else
    echo "⚠ Fail2ban is not running"
fi

echo ""
echo "=== DEPLOYMENT COMPLETED SUCCESSFULLY ==="
echo "Application URL: $APP_URL"
echo "Timestamp: $(date)"