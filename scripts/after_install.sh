#!/bin/bash
set -e

echo "=== AFTER INSTALL PHASE ==="
echo "Time: $(date)"

APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

# Suppress PHP deprecation warnings during deployment
export PHP_INI_SCAN_DIR=""
export PHPRC=""

# Function to run PHP commands without deprecation warnings
run_php_quiet() {
    php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" -d display_errors=0 -d log_errors=0 "$@" 2>/dev/null || php "$@"
}

# Function to run PHP commands as ec2-user without deprecation warnings
run_php_as_ec2user() {
    sudo -u ec2-user php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" -d display_errors=0 -d log_errors=0 "$@" 2>/dev/null || sudo -u ec2-user php "$@"
}

# Function to run artisan commands quietly as ec2-user
run_artisan_quiet() {
    run_php_as_ec2user artisan "$@" 2>/dev/null
}

echo "=== DEPLOYMENT VERIFICATION ==="
echo "Contents of /var/www after file copy:"
ls -la /var/www/ 2>/dev/null || echo "Cannot access /var/www"
echo ""
echo "Checking for critical files/directories:"
echo "- artisan file: $([ -f /var/www/artisan ] && echo "✓ Present" || echo "✗ Missing")"
echo "- app directory: $([ -d /var/www/app ] && echo "✓ Present" || echo "✗ Missing")"
echo "- .env.example: $([ -f /var/www/.env.example ] && echo "✓ Present" || echo "✗ Missing")"
echo ""

# Change to application directory  
cd $APP_DIR
echo "Working in directory: $(pwd)"

# Wait for file deployment to complete
echo "Waiting for application files to be fully deployed..."
WAIT_COUNT=0
MAX_WAIT=30

while [ $WAIT_COUNT -lt $MAX_WAIT ]; do
    if [ -f "artisan" ] && [ -f "composer.json" ] && [ -d "app" ] && [ -f ".env.example" ]; then
        echo "✓ Core application files detected"
        break
    fi
    echo "Waiting for files to be deployed... ($((WAIT_COUNT + 1))/$MAX_WAIT)"
    sleep 3
    WAIT_COUNT=$((WAIT_COUNT + 1))
done

if [ $WAIT_COUNT -eq $MAX_WAIT ]; then
    echo "ERROR: Timeout waiting for application files to be deployed"
    exit 1
fi

sleep 3

# ============================================================================
# STEP 1: CREATE .ENV FROM TEMPLATE
# ============================================================================
echo ""
echo "--- Creating .env from template ---"

# Remove existing .env
if [ -f "$ENV_FILE" ]; then
    rm -f "$ENV_FILE"
fi

# Copy .env.example to .env
if [ ! -f ".env.example" ]; then
    echo "ERROR: .env.example file not found!"
    exit 1
fi

cp ".env.example" "$ENV_FILE"
echo "✓ .env file created from .env.example template"

# Verify WebSocket config is preserved
if grep -q "WEBSOCKET_SERVER_HOST" "$ENV_FILE"; then
    echo "✓ WebSocket configuration preserved: $(grep "WEBSOCKET_SERVER_HOST" "$ENV_FILE")"
else
    echo "✗ WebSocket configuration missing from .env.example!"
fi

# ============================================================================
# STEP 2: APPLY AWS SECRETS  
# ============================================================================
echo ""
echo "--- Applying AWS Secrets Manager values ---"

# Fetch secrets
SECRET_JSON=$(aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text)

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to fetch AWS secrets"
    exit 1
fi

# Parse secrets
DB_HOST=$(echo "$SECRET_JSON" | jq -r '.DB_HOST')
DB_USER=$(echo "$SECRET_JSON" | jq -r '.DB_USER')
DB_DATABASE=$(echo "$SECRET_JSON" | jq -r '.DB_DATABASE')
DB_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.DB_PASSWORD')
OPENAI_API_KEY=$(echo "$SECRET_JSON" | jq -r '.OPENAI_API_KEY')
APP_URL=$(echo "$SECRET_JSON" | jq -r '.APP_URL')
DB_CONNECTION=$(echo "$SECRET_JSON" | jq -r '.DB_CONNECTION')
APP_KEY=$(echo "$SECRET_JSON" | jq -r '.APP_KEY')

# Debug: Show parsed secrets (excluding sensitive values)
echo "✓ Secrets parsed from AWS:"
echo "  DB_HOST: $DB_HOST"
echo "  DB_USER: $DB_USER"
echo "  DB_DATABASE: $DB_DATABASE"
echo "  APP_URL: $APP_URL"
echo "  DB_CONNECTION: $DB_CONNECTION"
echo "  APP_KEY: $(echo "$APP_KEY" | cut -c1-15)... (showing first 15 chars)"
echo "  OPENAI_API_KEY: $(echo "$OPENAI_API_KEY" | cut -c1-15)... (showing first 15 chars)"

# Simple function to update .env values
update_env() {
    local key=$1
    local value=$2
    local temp_file="/tmp/.env.tmp"
    
    # Create a backup of current .env
    cp "$ENV_FILE" "${ENV_FILE}.backup"
    
    if grep -q "^${key}=" "$ENV_FILE"; then
        # Update existing key
        sed "s|^${key}=.*|${key}=${value}|" "$ENV_FILE" > "$temp_file"
        mv "$temp_file" "$ENV_FILE"
    else
        # Add new key
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
    
    # Verify the file is still valid
    if [ ! -s "$ENV_FILE" ]; then
        echo "ERROR: .env file became empty after updating ${key}, restoring backup"
        mv "${ENV_FILE}.backup" "$ENV_FILE"
        exit 1
    fi
}

# Apply production settings
update_env "APP_ENV" "production"
update_env "APP_DEBUG" "false"
update_env "APP_URL" "$APP_URL"
echo "Applying APP_KEY from secrets: $(echo "$APP_KEY" | cut -c1-15)..."
update_env "APP_KEY" "$APP_KEY"

# Apply database settings  
update_env "DB_CONNECTION" "$DB_CONNECTION"
update_env "DB_HOST" "$DB_HOST"
update_env "DB_DATABASE" "$DB_DATABASE"
update_env "DB_USERNAME" "$DB_USER"
update_env "DB_PASSWORD" "\"$DB_PASSWORD\""

# Apply OpenAI settings
update_env "OPENAI_API_KEY" "$OPENAI_API_KEY"

# Ensure WebSocket configuration is preserved
if ! grep -q "WEBSOCKET_SERVER_HOST" "$ENV_FILE"; then
    echo "# WebSocket Server Configuration" >> "$ENV_FILE"
    echo "WEBSOCKET_SERVER_HOST=TESTING" >> "$ENV_FILE"
    echo "⚠ WebSocket configuration was missing - added back from template"
fi

echo "✓ AWS Secrets applied to .env"

# Verify all critical secrets were applied correctly
echo "Post-application verification:"
echo "  APP_KEY in .env: $(grep "^APP_KEY=" "$ENV_FILE" | cut -c1-20)... (showing first 20 chars)"
echo "  DB_HOST in .env: $(grep "^DB_HOST=" "$ENV_FILE")"
echo "  APP_URL in .env: $(grep "^APP_URL=" "$ENV_FILE")"

# Verify WebSocket config exists
if grep -q "WEBSOCKET_SERVER_HOST" "$ENV_FILE"; then
    echo "✓ WebSocket configuration preserved after secrets: $(grep "WEBSOCKET_SERVER_HOST" "$ENV_FILE")"
else
    echo "✗ WebSocket configuration LOST after applying secrets!"
    exit 1
fi

# ============================================================================
# STEP 3: INSTALL DEPENDENCIES
# ============================================================================
echo ""
echo "--- Installing dependencies ---"

# Install composer dependencies if needed
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
    echo "✓ Composer dependencies installed"
fi

# Check if Reverb is available and install if needed
echo "Checking Laravel Reverb availability..."

# First check if the package is installed
echo "Checking if Laravel Reverb package is installed..."
if composer show | grep -q "laravel/reverb"; then
    echo "✓ Laravel Reverb package is installed"
    REVERB_VERSION=$(composer show laravel/reverb | grep versions | head -1)
    echo "  Version: $REVERB_VERSION"
else
    echo "✗ Laravel Reverb package not found in installed packages"
fi

# Check if Reverb service provider is loaded
echo "Checking if Reverb service provider is registered..."
if run_artisan_quiet config:show | grep -q "reverb" 2>/dev/null; then
    echo "✓ Reverb configuration is available"
else
    echo "⚠ Reverb configuration not found"
fi

if ! run_artisan_quiet list | grep -q "reverb:"; then
    echo "⚠ Reverb commands not available, attempting to install..."
    # Try to install Reverb if it's missing
    run_artisan_quiet reverb:install --without-comments || echo "⚠ Reverb install failed or not available"
    
    # Clear and rebuild caches
    run_artisan_quiet config:clear || true
    run_artisan_quiet cache:clear || true
    composer dump-autoload --optimize --no-dev || true
    
    # Check again
    if ! run_artisan_quiet list | grep -q "reverb:"; then
        echo "⚠ Reverb commands still not available after installation attempt"
        echo "Available artisan commands:"
        run_artisan_quiet list | grep -E "(websocket|broadcast|reverb)" || echo "No websocket/broadcast related commands found"
    else
        echo "✓ Reverb commands now available"
    fi
else
    echo "✓ Reverb commands are available"
fi

# Verify APP_KEY is set from secrets
if grep -q "^APP_KEY=base64:" "$ENV_FILE"; then
    echo "✓ APP_KEY successfully applied from AWS Secrets Manager"
else
    echo "⚠ APP_KEY not properly set from AWS Secrets Manager"
    echo "Current APP_KEY line: $(grep "APP_KEY" "$ENV_FILE" || echo 'APP_KEY line not found')"
fi

# ============================================================================
# STEP 4: UPDATE APACHE CONFIGURATION
# ============================================================================
echo ""
echo "--- Updating Apache configuration ---"

DOMAIN=$(echo "$APP_URL" | sed -E 's|^https?://||' | sed 's|/.*$||')
echo "Domain: $DOMAIN"

APACHE_CONF="/var/www/config/apache/learningjourneys.conf"
if [ -f "$APACHE_CONF" ]; then
    # Update Apache configuration with dynamic domain
    sed -i "s/ServerName .*/ServerName $DOMAIN/" "$APACHE_CONF"
    sed -i "s/ServerAlias .*/ServerAlias www.$DOMAIN/" "$APACHE_CONF"
    
    # Copy to Apache directory
    cp "$APACHE_CONF" "/etc/httpd/conf.d/learningjourneys.conf"
    echo "✓ Apache configuration updated"
fi

# ============================================================================
# STEP 5: SET PERMISSIONS
# ============================================================================
echo ""
echo "--- Setting permissions ---"

# Set ownership
chown -R ec2-user:apache "$APP_DIR"
echo "✓ Ownership set to ec2-user:apache"

# Set file permissions
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;

# Set special permissions for storage and cache directories
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Ensure logs directory exists and has correct permissions
echo "Setting up Laravel logs directory..."
mkdir -p "$APP_DIR/storage/logs"
mkdir -p "$APP_DIR/storage/framework/cache"
mkdir -p "$APP_DIR/storage/framework/sessions"
mkdir -p "$APP_DIR/storage/framework/views"
mkdir -p "$APP_DIR/storage/app/public"

chown -R ec2-user:apache "$APP_DIR/storage/logs"
chmod -R 775 "$APP_DIR/storage/logs"

# Create initial log file if it doesn't exist
if [ ! -f "$APP_DIR/storage/logs/laravel.log" ]; then
    touch "$APP_DIR/storage/logs/laravel.log"
    chown ec2-user:apache "$APP_DIR/storage/logs/laravel.log"
    chmod 664 "$APP_DIR/storage/logs/laravel.log"
    echo "✓ Created initial laravel.log file"
fi

# Ensure log files have correct ownership if they exist
if [ -d "$APP_DIR/storage/logs" ]; then
    chown -R ec2-user:apache "$APP_DIR/storage/logs"
    chmod -R 775 "$APP_DIR/storage/logs"
    # Fix any existing log files that might be owned by root
    if [ -f "$APP_DIR/storage/logs/laravel.log" ]; then
        chown ec2-user:apache "$APP_DIR/storage/logs/laravel.log"
        chmod 664 "$APP_DIR/storage/logs/laravel.log"
    fi
    echo "✓ Log directory permissions set correctly"
fi

# Set special permissions
chmod +x "$APP_DIR/artisan"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"
chmod 600 "$ENV_FILE"

if [ -d "$APP_DIR/scripts" ]; then
    chmod +x "$APP_DIR/scripts"/*.sh
fi

echo "✓ Permissions set correctly"

# ============================================================================
# STEP 6: OPTIMIZE LARAVEL
# ============================================================================
echo ""
echo "--- Optimizing Laravel ---"

run_artisan_quiet config:clear || echo "⚠ Config clear failed"
run_artisan_quiet route:clear || echo "⚠ Route clear failed" 
run_artisan_quiet view:clear || echo "⚠ View clear failed"
run_artisan_quiet cache:clear || echo "⚠ Cache clear failed"
run_artisan_quiet config:cache || echo "⚠ Config cache failed"
run_artisan_quiet route:cache || echo "⚠ Route cache failed"
run_artisan_quiet view:cache || echo "⚠ View cache failed"

echo "✓ Laravel optimization completed"

# Fix ownership after running Laravel commands
echo "--- Fixing ownership after Laravel commands ---"
chown -R ec2-user:apache "$APP_DIR/storage"
chown -R ec2-user:apache "$APP_DIR/bootstrap/cache"
if [ -f "$APP_DIR/storage/logs/laravel.log" ]; then
    chown ec2-user:apache "$APP_DIR/storage/logs/laravel.log"
    chmod 664 "$APP_DIR/storage/logs/laravel.log"
    echo "✓ Fixed laravel.log ownership"
fi
echo "✓ Ownership correction completed"

# Test logging functionality
echo "--- Testing Laravel logging ---"
echo "Testing if Laravel can write to log file..."

# Create a simple test script to verify logging
cat > /tmp/test_logging.php << 'EOF'
<?php
require_once '/var/www/vendor/autoload.php';
$app = require_once '/var/www/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    \Log::info('Deployment test log entry - ' . date('Y-m-d H:i:s'));
    echo "Log test successful\n";
} catch (Exception $e) {
    echo "Log test failed: " . $e->getMessage() . "\n";
}
EOF

# Run the logging test
run_php_as_ec2user /tmp/test_logging.php || echo "⚠ Direct log test failed"
rm -f /tmp/test_logging.php

# Alternative: try artisan tinker
run_artisan_quiet tinker --execute="Log::info('Deployment test log entry - $(date)');" || echo "⚠ Artisan log test failed"

# Verify log file was created and is writable
if [ -f "$APP_DIR/storage/logs/laravel.log" ]; then
    LOG_SIZE=$(stat -c%s "$APP_DIR/storage/logs/laravel.log" 2>/dev/null || echo "0")
    LOG_OWNER=$(ls -la "$APP_DIR/storage/logs/laravel.log" | awk '{print $3":"$4}')
    LOG_PERMS=$(ls -la "$APP_DIR/storage/logs/laravel.log" | awk '{print $1}')
    echo "✓ Laravel log file exists:"
    echo "  Size: $LOG_SIZE bytes"
    echo "  Owner: $LOG_OWNER"
    echo "  Permissions: $LOG_PERMS"
    
    # Show last few lines if file has content
    if [ "$LOG_SIZE" -gt 0 ]; then
        echo "  Last log entries:"
        tail -3 "$APP_DIR/storage/logs/laravel.log" | sed 's/^/    /'
    fi
else
    echo "⚠ Laravel log file was not created"
fi

# ============================================================================
# FINAL VERIFICATION
# ============================================================================
echo ""
echo "--- Final verification ---"
echo "✓ .env file size: $(wc -c < "$ENV_FILE") bytes"
echo "✓ .env file lines: $(wc -l < "$ENV_FILE") lines"

echo "Comprehensive .env configuration check:"

# Check critical configurations
MISSING_CONFIGS=""

if ! grep -q "WEBSOCKET_SERVER_HOST" "$ENV_FILE"; then
    MISSING_CONFIGS="$MISSING_CONFIGS WEBSOCKET_SERVER_HOST"
fi

if ! grep -q "OPENAI_API_KEY=" "$ENV_FILE"; then
    MISSING_CONFIGS="$MISSING_CONFIGS OPENAI_API_KEY"
fi

if ! grep -q "DB_HOST=" "$ENV_FILE"; then
    MISSING_CONFIGS="$MISSING_CONFIGS DB_HOST"
fi

if ! grep -q "APP_URL=" "$ENV_FILE"; then
    MISSING_CONFIGS="$MISSING_CONFIGS APP_URL"
fi

if ! grep -q "APP_KEY=" "$ENV_FILE"; then
    MISSING_CONFIGS="$MISSING_CONFIGS APP_KEY"
fi

if [ -n "$MISSING_CONFIGS" ]; then
    echo "✗ Missing configurations:$MISSING_CONFIGS"
    echo "Showing last 10 lines of .env file for debugging:"
    tail -10 "$ENV_FILE"
    exit 1
fi

echo "✓ WEBSOCKET_SERVER_HOST: $(grep "WEBSOCKET_SERVER_HOST" "$ENV_FILE")"
echo "✓ DB_HOST: $(grep "^DB_HOST=" "$ENV_FILE")"
echo "✓ APP_URL: $(grep "^APP_URL=" "$ENV_FILE")"
echo "✓ APP_KEY: $(grep "^APP_KEY=" "$ENV_FILE" | cut -c1-20)..." # Show only first 20 chars for security
echo "✓ All critical configurations present"

echo ""
echo "--- Final Log System Check ---"
# Ensure log system is ready for runtime
if [ -f "$APP_DIR/storage/logs/laravel.log" ]; then
    # Make sure the log file will persist and be accessible
    chown ec2-user:apache "$APP_DIR/storage/logs/laravel.log"
    chmod 664 "$APP_DIR/storage/logs/laravel.log"
    
    # Test one final write to ensure everything works
    run_artisan_quiet tinker --execute="Log::info('Final deployment verification - Application ready');" || echo "⚠ Final log test failed"
    
    FINAL_SIZE=$(stat -c%s "$APP_DIR/storage/logs/laravel.log")
    echo "✓ Log system ready - file size: $FINAL_SIZE bytes"
    echo "  Log file path: $APP_DIR/storage/logs/laravel.log"
    echo "  Log file permissions: $(ls -la "$APP_DIR/storage/logs/laravel.log" | awk '{print $1, $3":"$4}')"
else
    echo "⚠ Log file missing at end of deployment"
fi

echo ""
echo "=== AFTER INSTALL COMPLETED SUCCESSFULLY ==="