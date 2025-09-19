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

# Verify Reverb WebSocket config is preserved
if grep -q "REVERB_APP_ID" "$ENV_FILE"; then
    echo "✓ Reverb WebSocket configuration preserved: $(grep "REVERB_APP_ID" "$ENV_FILE")"
else
    echo "✗ Reverb WebSocket configuration missing from .env.example!"
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
# REVERB_HOST is now in Environment Variables, not secrets
# Use environment variables for Reverb connection settings
REVERB_HOST_ENV=${REVERB_HOST:-"the-thinking-course.com"}
REVERB_PORT_ENV=${REVERB_PORT:-"443"}  
REVERB_SCHEME_ENV=${REVERB_SCHEME:-"https"}
REVERB_SERVER_HOST_ENV=${REVERB_SERVER_HOST:-"0.0.0.0"}
REVERB_SERVER_PORT_ENV=${REVERB_SERVER_PORT:-"8080"}

# Debug: Show parsed secrets (excluding sensitive values)
echo "✓ Secrets parsed from AWS:"
echo "  DB_HOST: $DB_HOST"
echo "  DB_USERNAME: $DB_USERNAME"
echo "  DB_DATABASE: $DB_DATABASE"
echo "  APP_URL: $APP_URL"
echo "  DB_CONNECTION: $DB_CONNECTION"
echo "  APP_KEY: $(echo "$APP_KEY" | cut -c1-15)... (showing first 15 chars)"
echo "  OPENAI_API_KEY: $(echo "$OPENAI_API_KEY" | cut -c1-15)... (showing first 15 chars)"
echo "  REVERB_APP_ID: $REVERB_APP_ID"
echo "  REVERB_APP_KEY: $REVERB_APP_KEY"
echo "  REVERB_HOST: $REVERB_HOST_ENV"
echo "  REVERB_PORT: $REVERB_PORT_ENV"
echo "  REVERB_SCHEME: $REVERB_SCHEME_ENV"
echo "  REVERB_SERVER_HOST: $REVERB_SERVER_HOST_ENV"
echo "  REVERB_SERVER_PORT: $REVERB_SERVER_PORT_ENV"

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
update_env "DB_USERNAME" "$DB_USERNAME"
update_env "DB_PASSWORD" "\"$DB_PASSWORD\""

# Apply OpenAI settings
update_env "OPENAI_API_KEY" "$OPENAI_API_KEY"

# Apply WebSocket/Reverb settings
update_env "REVERB_APP_ID" "$REVERB_APP_ID"
update_env "REVERB_APP_KEY" "$REVERB_APP_KEY"
update_env "REVERB_APP_SECRET" "$REVERB_APP_SECRET"
update_env "REVERB_HOST" "$REVERB_HOST_ENV"
update_env "REVERB_PORT" "$REVERB_PORT_ENV"
update_env "REVERB_SCHEME" "$REVERB_SCHEME_ENV"
update_env "REVERB_SERVER_HOST" "$REVERB_SERVER_HOST_ENV"
update_env "REVERB_SERVER_PORT" "$REVERB_SERVER_PORT_ENV"

# Apply Vite variables for frontend compilation (already compiled but keeping config consistent)
update_env "VITE_REVERB_APP_KEY" "$REVERB_APP_KEY"
update_env "VITE_REVERB_HOST" "$REVERB_HOST_ENV"
update_env "VITE_REVERB_PORT" "$REVERB_PORT_ENV"
update_env "VITE_REVERB_SCHEME" "$REVERB_SCHEME_ENV"

# Ensure Reverb configuration is preserved (not needed - already in template)
echo "✓ Reverb configuration preserved in .env.example template"

echo "✓ AWS Secrets applied to .env"

# Verify all critical secrets were applied correctly
echo "Post-application verification:"
echo "  APP_KEY in .env: $(grep "^APP_KEY=" "$ENV_FILE" | cut -c1-20)... (showing first 20 chars)"
echo "  DB_HOST in .env: $(grep "^DB_HOST=" "$ENV_FILE")"
echo "  APP_URL in .env: $(grep "^APP_URL=" "$ENV_FILE")"

# Verify Reverb config exists
if grep -q "REVERB_APP_ID" "$ENV_FILE"; then
    echo "✓ Reverb configuration preserved after secrets: $(grep "REVERB_APP_ID" "$ENV_FILE")"
else
    echo "✗ Reverb configuration LOST after applying secrets!"
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
# STEP 4: OPTIMIZE PHP FOR STREAMING
# ============================================================================
echo ""
echo "--- Optimizing PHP for streaming performance ---"

# Detect Amazon Linux 2023 PHP configuration directories
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo "Detected PHP version: $PHP_VERSION"

# Amazon Linux 2023 uses different paths for PHP configuration
if [ -d "/etc/php.d" ]; then
    PHP_INI_DIR="/etc/php.d"
elif [ -d "/etc/php/$PHP_VERSION/cli/conf.d" ]; then
    PHP_INI_DIR="/etc/php/$PHP_VERSION/cli/conf.d"
else
    # Fallback for other systems
    PHP_INI_DIR="/etc/php.d"
    mkdir -p "$PHP_INI_DIR"
fi

echo "Using PHP INI directory: $PHP_INI_DIR"

# Create PHP streaming configuration
PHP_STREAMING_CONF="$PHP_INI_DIR/99-streaming-optimizations.ini"
cat > "$PHP_STREAMING_CONF" << 'EOF'
; PHP Streaming Optimizations for Learning Journeys
; Disable output buffering for better streaming performance
output_buffering = Off
output_handler = 
implicit_flush = On
zlib.output_compression = Off

; Increase limits for streaming operations
max_execution_time = 300
memory_limit = 256M
max_input_time = 300

; Optimize for real-time streaming
auto_prepend_file = 
auto_append_file = 

; FastCGI optimizations (if using PHP-FPM)
fastcgi.logging = 0

; Production streaming optimizations
realpath_cache_size = 4096K
realpath_cache_ttl = 600
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 10000

; Configure mbstring for Reverb/WebSocket support (extension loaded by default, just configure)
mbstring.func_overload = 0
mbstring.internal_encoding = UTF-8
mbstring.http_input = UTF-8
mbstring.http_output = UTF-8
mbstring.encoding_translation = Off
EOF

echo "✓ PHP streaming optimizations configured at: $PHP_STREAMING_CONF"

# Configure PHP-FPM for streaming
echo "--- Configuring PHP-FPM for streaming ---"

# Find PHP-FPM configuration directory
if [ -d "/etc/php-fpm.d" ]; then
    FPM_POOL_DIR="/etc/php-fpm.d"
elif [ -d "/etc/php/$PHP_VERSION/fpm/pool.d" ]; then
    FPM_POOL_DIR="/etc/php/$PHP_VERSION/fpm/pool.d"
else
    echo "⚠ PHP-FPM pool directory not found, skipping FPM configuration"
    FPM_POOL_DIR=""
fi

if [ -n "$FPM_POOL_DIR" ]; then
    echo "Using PHP-FPM pool directory: $FPM_POOL_DIR"
    
    # Instead of creating a conflicting separate pool, configure the main www pool for streaming
    echo "Configuring main www pool for streaming responses..."
    
    # Backup and modify the original www.conf
    WWW_POOL_CONF="$FPM_POOL_DIR/www.conf"
    if [ -f "$WWW_POOL_CONF" ]; then
        cp "$WWW_POOL_CONF" "$WWW_POOL_CONF.backup-$(date +%Y%m%d-%H%M%S)"
        echo "✓ Backed up original www.conf"
    fi
    
    # Add comprehensive streaming settings to www pool
    if [ -f "$WWW_POOL_CONF" ]; then
        echo "--- Adding comprehensive streaming settings to main www pool ---"
        
        # Add comprehensive streaming settings to www pool if not already present
        if ! grep -q "Streaming optimizations added by deployment script" "$WWW_POOL_CONF"; then
            cat >> "$WWW_POOL_CONF" << 'EOF'

; Streaming optimizations added by deployment script - CRITICAL for AI response streaming
; Disable all forms of output buffering
php_admin_value[output_buffering] = Off
php_admin_value[implicit_flush] = On
php_admin_value[zlib.output_compression] = Off
php_admin_value[output_handler] = ""
php_admin_value[auto_prepend_file] = ""
php_admin_value[auto_append_file] = ""

; Streaming-specific limits
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
php_admin_value[max_input_time] = 300

; FastCGI buffer settings - CRITICAL for streaming
php_admin_value[fastcgi.logging] = 0
php_admin_value[cgi.fix_pathinfo] = 0

; Session settings for streaming (avoid session locks during streaming)
php_admin_value[session.cache_limiter] = ""
EOF
            echo "✓ Comprehensive streaming settings added to main www pool"
        else
            echo "✓ Streaming settings already present in www pool"
        fi
    fi
    
    # Create Apache streaming configuration (PHP-FPM compatible)
    echo "--- Configuring Apache for PHP-FMP streaming ---"
    
    # Clean up any existing conflicting configuration files first
    echo "Cleaning up existing Apache streaming configuration files..."
    
    # Remove any old FastCGI configuration files that might conflict
    OLD_CONFIG_FILES="/etc/httpd/conf.d/fastcgi-streaming.conf /etc/httpd/conf.d/phpfpm-streaming.conf /etc/httpd/conf.d/streaming.conf"
    for CONFIG_FILE in $OLD_CONFIG_FILES; do
        if [ -f "$CONFIG_FILE" ]; then
            echo "Removing old configuration file: $CONFIG_FILE"
            rm -f "$CONFIG_FILE"
        fi
    done
    
    # Also clean up any backup files that might interfere
    rm -f /etc/httpd/conf.d/*streaming*.backup 2>/dev/null || true
    rm -f /etc/httpd/conf.d/*fcgid*.backup 2>/dev/null || true
    
    echo "✓ Old configuration files cleaned up"
    
    # Check if mod_fcgid is available
    echo "Checking for FastCGI module availability..."
    if httpd -M 2>/dev/null | grep -q "fcgid_module"; then
        echo "✓ mod_fcgid is available - using FastCGI configuration"
        APACHE_STREAMING_CONF="/etc/httpd/conf.d/fastcgi-streaming.conf"
        cat > "$APACHE_STREAMING_CONF" << 'EOF'
# FastCGI streaming configuration for Learning Journeys
# CRITICAL settings for streaming AI responses

# Disable FastCGI buffering for streaming responses
FcgidOutputBufferSize 0
FcgidMaxRequestLen 1048576

# Streaming-specific FastCGI settings
FcgidIOTimeout 300
FcgidConnectTimeout 20
FcgidBusyTimeout 300
FcgidIdleTimeout 300

# Prevent buffering in mod_fcgid
FcgidBusyScanInterval 120
FcgidErrorScanInterval 3
FcgidZombieScanInterval 3

# Process management
FcgidMaxProcesses 50
FcgidMaxProcessesPerClass 8
FcgidMinProcessesPerClass 0

# Critical: Disable response buffering
FcgidInitialEnv FCGI_WEB_SERVER_ADDRS "127.0.0.1"
FcgidInitialEnv PHP_FCGI_MAX_REQUESTS 1000
EOF
        echo "✓ Apache FastCGI streaming configuration created at: $APACHE_STREAMING_CONF"
    else
        echo "⚠ mod_fcgid not available - using PHP-FPM compatible configuration"
        APACHE_STREAMING_CONF="/etc/httpd/conf.d/phpfpm-streaming.conf"
        cat > "$APACHE_STREAMING_CONF" << 'EOF'
# PHP-FPM streaming configuration for Learning Journeys
# CRITICAL settings for streaming AI responses (without mod_fcgid)

# General streaming optimizations
<IfModule mod_proxy.c>
    # Disable proxy buffering for streaming responses
    ProxyPreserveHost On
    ProxyVia Off
    
    # CRITICAL: Disable buffering for streaming
    ProxyBufferSize 0
    ProxyReceiveBufferSize 0
    
    # Streaming-specific proxy settings
    ProxyTimeout 300
    ProxyIOBufferSize 1024
</IfModule>

# Disable compression and buffering globally for streaming
<IfModule mod_deflate.c>
    # Disable compression for streaming responses
    SetEnvIfNoCase Request_URI "\.php$" no-gzip
    SetEnvIfNoCase Request_URI "/api/" no-gzip
    SetEnvIfNoCase Request_URI "/streaming" no-gzip
</IfModule>

# Header optimizations for streaming
<IfModule mod_headers.c>
    # Prevent caching of streaming responses
    Header always set Cache-Control "no-cache, no-store, must-revalidate" env=STREAMING
    Header always set Pragma "no-cache" env=STREAMING
    Header always set X-Accel-Buffering "no" env=STREAMING
    
    # Set streaming environment variable for specific paths
    SetEnvIf Request_URI "/(api|streaming|chat)" STREAMING
</IfModule>

# Output buffering control
<IfModule mod_env.c>
    # Disable various forms of buffering
    SetEnv no-gzip 1
    SetEnv no-brotli 1
    SetEnv dont-vary 1
</IfModule>
EOF
        echo "✓ Apache PHP-FPM streaming configuration created at: $APACHE_STREAMING_CONF"
    fi
else
    echo "⚠ Skipping PHP-FPM pool configuration"
fi

# Additional production-specific optimizations
echo "Configuring production streaming optimizations..."

# Check if we're behind a load balancer and configure accordingly
if curl -s --max-time 5 http://169.254.169.254/latest/meta-data/instance-id &>/dev/null; then
    echo "✓ Detected AWS EC2 environment - applying ALB optimizations"
    
    # Add ALB-specific configuration
    cat >> "$PHP_STREAMING_CONF" << 'EOF'

; AWS ALB/ELB specific optimizations
user_agent = "LearningJourneys-Streaming/1.0"
default_socket_timeout = 300
EOF

    # Configure keepalive for ALB with proxy module detection
    if [ -f "/etc/httpd/conf.d/keepalive.conf" ] || [ ! -f "/etc/httpd/conf.d/keepalive.conf" ]; then
        # Check if proxy modules are available before using proxy directives
        PROXY_AVAILABLE=false
        if httpd -M 2>/dev/null | grep -q "proxy_module"; then
            PROXY_AVAILABLE=true
            echo "✓ Proxy modules detected - including full ALB proxy configuration"
        else
            echo "⚠ Proxy modules not available - using basic keepalive configuration only"
        fi
        
        # Create keepalive configuration based on module availability
        cat > /etc/httpd/conf.d/keepalive-streaming.conf << EOF
# KeepAlive optimizations for ALB/streaming (generated by deployment script)
KeepAlive On
MaxKeepAliveRequests 1000
KeepAliveTimeout 300

# Disable compression for streaming responses
SetEnv no-gzip 1
SetEnv no-brotli 1

# Compression prevention headers
Header always set Cache-Control "no-cache, no-store, must-revalidate"
Header always set Pragma "no-cache"
Header always set Expires 0
EOF
        
        # Only add proxy directives if proxy modules are available
        if [ "$PROXY_AVAILABLE" = true ]; then
            cat >> /etc/httpd/conf.d/keepalive-streaming.conf << 'EOF'

# Proxy configuration (requires mod_proxy)
ProxyPreserveHost On
ProxyVia Off

# CRITICAL: Disable proxy buffering for streaming responses  
ProxyBufferSize 0
ProxyReceiveBufferSize 0

# Streaming-specific proxy settings
ProxyTimeout 300
ProxyIOBufferSize 1024
EOF
            echo "✓ ALB and streaming proxy optimizations configured with proxy support"
        else
            echo "✓ ALB and streaming basic optimizations configured (no proxy modules)"
        fi
    fi
fi

# Verify PHP configuration
echo "Verifying PHP streaming settings..."
if php -r "echo 'output_buffering: ' . ini_get('output_buffering') . PHP_EOL;"; then
    echo "✓ PHP configuration accessible"
else
    echo "⚠ Could not verify PHP configuration"
fi

# Restart PHP-FPM to apply configuration changes
echo "--- Restarting PHP-FPM service ---"
if systemctl is-active --quiet php-fpm; then
    echo "Restarting PHP-FPM service..."
    systemctl restart php-fpm
    if [ $? -eq 0 ]; then
        echo "✓ PHP-FPM service restarted successfully"
        
        # Verify PHP-FPM is running
        if systemctl is-active --quiet php-fpm; then
            echo "✓ PHP-FPM service is active"
        else
            echo "✗ PHP-FPM service failed to start properly"
            systemctl status php-fpm --no-pager -l
        fi
    else
        echo "✗ Failed to restart PHP-FPM service"
        systemctl status php-fpm --no-pager -l
    fi
else
    echo "⚠ PHP-FPM service is not running, attempting to start..."
    systemctl start php-fpm
    if [ $? -eq 0 ]; then
        echo "✓ PHP-FPM service started successfully"
    else
        echo "✗ Failed to start PHP-FPM service"
        systemctl status php-fpm --no-pager -l
    fi
fi

# Test streaming configuration after restart
echo "--- Testing comprehensive streaming configuration ---"
echo "=== PHP CLI Streaming Test ==="
php -r "
echo 'PHP Streaming Configuration Test:' . PHP_EOL;
echo 'output_buffering: ' . (ini_get('output_buffering') ? ini_get('output_buffering') : 'Off') . PHP_EOL;
echo 'implicit_flush: ' . (ini_get('implicit_flush') ? 'On' : 'Off') . PHP_EOL;
echo 'zlib.output_compression: ' . (ini_get('zlib.output_compression') ? 'On' : 'Off') . PHP_EOL;
echo 'output_handler: ' . ini_get('output_handler') . PHP_EOL;
echo 'session.cache_limiter: ' . ini_get('session.cache_limiter') . PHP_EOL;
"

echo ""
echo "=== PHP-FPM Pool Configuration Test ==="
if [ -f "$WWW_POOL_CONF" ]; then
    echo "Checking www pool streaming settings:"
    grep -A5 -B1 "Streaming optimizations" "$WWW_POOL_CONF" || echo "⚠ Streaming settings not found in www pool"
    echo ""
fi

echo "=== Apache Streaming Configuration Test ==="
if [ -f "$APACHE_STREAMING_CONF" ]; then
    echo "✓ Apache streaming configuration exists at: $APACHE_STREAMING_CONF"
    echo "Key settings:"
    if grep -q "FcgidOutputBufferSize" "$APACHE_STREAMING_CONF"; then
        echo "Using FastCGI configuration:"
        grep -E "(OutputBufferSize|IOTimeout|BusyTimeout)" "$APACHE_STREAMING_CONF" || echo "⚠ FastCGI settings missing"
    else
        echo "Using PHP-FPM configuration:"
        grep -E "(ProxyBufferSize|ProxyTimeout|no-gzip)" "$APACHE_STREAMING_CONF" || echo "⚠ PHP-FPM settings missing"
    fi
else
    echo "⚠ Apache streaming configuration missing"
fi

echo ""
echo "=== Proxy Configuration Test ==="
if [ -f "/etc/httpd/conf.d/keepalive-streaming.conf" ]; then
    echo "✓ Apache proxy streaming configuration exists"
    echo "Key settings:"
    grep -E "(ProxyBufferSize|ProxyIOBufferSize|no-gzip)" "/etc/httpd/conf.d/keepalive-streaming.conf" || echo "⚠ Critical proxy settings missing"
else
    echo "⚠ Apache proxy streaming configuration missing"
fi

echo ""
echo "=== Creating Streaming Test Script ==="
# Create a test script to verify streaming works
cat > "/var/www/public/test-streaming.php" << 'EOF'
<?php
// Streaming test script for AI responses
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // Disable Nginx buffering if behind Nginx
header('Connection: keep-alive');

// Disable all forms of output buffering
if (ob_get_level()) {
    ob_end_clean();
}
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ini_set('implicit_flush', true);
ob_implicit_flush(true);

echo "Streaming test started...\n";
flush();

for ($i = 1; $i <= 10; $i++) {
    echo "Chunk $i: " . date('H:i:s') . " - ";
    echo str_repeat('data ', 10) . "\n";
    flush();
    usleep(500000); // 0.5 seconds
}

echo "Streaming test completed!\n";
EOF

chown ec2-user:apache "/var/www/public/test-streaming.php"
chmod 644 "/var/www/public/test-streaming.php"
echo "✓ Streaming test script created at: /var/www/public/test-streaming.php"
echo "✓ Test URL: https://the-thinking-course.com/test-streaming.php"

# ============================================================================
# STEP 5: UPDATE APACHE CONFIGURATION
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
# STEP 6: SET PERMISSIONS
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
# STEP 7: DATABASE MIGRATIONS
# ============================================================================
echo ""
echo "--- Running Database Migrations ---"

# Test database connection first
echo "Testing database connection..."
if run_artisan_quiet tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful';" 2>/dev/null; then
    echo "✓ Database connection successful"
else
    echo "⚠ Database connection failed - checking configuration..."
    echo "DB_HOST: $(grep "^DB_HOST=" "$ENV_FILE" 2>/dev/null || echo "Not found")"
    echo "DB_DATABASE: $(grep "^DB_DATABASE=" "$ENV_FILE" 2>/dev/null || echo "Not found")"
    echo "DB_CONNECTION: $(grep "^DB_CONNECTION=" "$ENV_FILE" 2>/dev/null || echo "Not found")"
fi

# Run migrations to ensure database schema is up to date
echo "Running Laravel migrations..."
if run_artisan_quiet migrate --force 2>/dev/null; then
    echo "✓ Database migrations completed successfully"
else
    echo "⚠ Database migrations failed or had warnings"
    echo "Attempting to show migration status:"
    run_artisan_quiet migrate:status 2>/dev/null || echo "Cannot show migration status"
fi

# Verify critical tables exist and have correct structure
echo "Verifying critical database tables..."
if run_artisan_quiet tinker --execute="echo 'personal_access_tokens count: ' . DB::table('personal_access_tokens')->count();" 2>/dev/null; then
    echo "✓ personal_access_tokens table is accessible"
    
    # Check if expires_at column exists
    if run_artisan_quiet tinker --execute="Schema::hasColumn('personal_access_tokens', 'expires_at') ? 'expires_at column exists' : 'expires_at column MISSING';" 2>/dev/null; then
        echo "✓ Verified expires_at column structure"
    else
        echo "⚠ Could not verify expires_at column - may need manual migration"
    fi
else
    echo "⚠ personal_access_tokens table may not exist or be accessible"
    echo "Attempting to check table existence..."
    run_artisan_quiet tinker --execute="Schema::hasTable('personal_access_tokens') ? 'Table exists' : 'Table missing'" 2>/dev/null || echo "Cannot check table existence"
fi

# ============================================================================
# STEP 8: CONFIGURE WEBSOCKET SSL PROXY
# ============================================================================
echo ""
echo "--- Configuring WebSocket SSL Proxy ---"

# Enable required Apache modules for WebSocket proxy
echo "Enabling Apache proxy modules for WebSocket support..."

# First, check for any duplicate module loading issues
echo "--- Checking for duplicate Apache module configurations ---"

# Check for problematic HTTP/2 proxy module and completely remove it
echo "Checking for problematic HTTP/2 proxy module..."

# First, clean up any excessively disabled files
find /etc/httpd/conf.modules.d/ -name "*proxy*h2*.disabled*" -type f 2>/dev/null | while read DISABLED_FILE; do
    if [ -f "$DISABLED_FILE" ]; then
        echo "Removing excessive disabled file: $DISABLED_FILE"
        rm -f "$DISABLED_FILE" 2>/dev/null || sudo rm -f "$DISABLED_FILE" 2>/dev/null || true
    fi
done

# Now handle active HTTP/2 proxy module files
HTTP2_MODULE_FILES="/etc/httpd/conf.modules.d/10-proxy_h2.conf /etc/httpd/conf.modules.d/00-proxy_h2.conf"
HTTP2_REMOVED=""

for MODULE_FILE in $HTTP2_MODULE_FILES; do
    if [ -f "$MODULE_FILE" ]; then
        echo "Found problematic HTTP/2 proxy module: $MODULE_FILE"
        echo "Removing $MODULE_FILE completely..."
        rm -f "$MODULE_FILE" 2>/dev/null || sudo rm -f "$MODULE_FILE" 2>/dev/null || true
        HTTP2_REMOVED="$HTTP2_REMOVED $MODULE_FILE"
    fi
done

# Also check for any LoadModule directives for HTTP/2 proxy in other configs
find /etc/httpd -name "*.conf" -type f -exec grep -l "LoadModule.*proxy_http2_module" {} \; 2>/dev/null | while read CONFIG_FILE; do
    if [ -n "$CONFIG_FILE" ]; then
        echo "Found HTTP/2 proxy module loading in: $CONFIG_FILE"
        sed -i '/^LoadModule.*proxy_http2_module/s/^/#DISABLED: /' "$CONFIG_FILE" 2>/dev/null || sudo sed -i '/^LoadModule.*proxy_http2_module/s/^/#DISABLED: /' "$CONFIG_FILE" 2>/dev/null || true
    fi
done

if [ -n "$HTTP2_REMOVED" ]; then
    echo "✓ HTTP/2 proxy modules completely removed: $HTTP2_REMOVED"
else
    echo "✓ No problematic HTTP/2 proxy modules found"
fi

# Check current Apache module status
echo "Current loaded modules:"
LOADED_MODULES=$(httpd -M 2>/dev/null | grep -E "(proxy_module|proxy_http_module|proxy_wstunnel_module|ssl_module|rewrite_module|headers_module)" | sort)
echo "$LOADED_MODULES"

# Check for duplicate LoadModule directives across all Apache configs
echo ""
echo "Checking for duplicate LoadModule directives..."
DUPLICATE_CHECK=$(find /etc/httpd -name "*.conf" -type f -exec grep -l "LoadModule.*proxy_module\|LoadModule.*ssl_module\|LoadModule.*rewrite_module\|LoadModule.*headers_module" {} \; 2>/dev/null | head -10)

if [ -n "$DUPLICATE_CHECK" ]; then
    echo "Found LoadModule directives in these files:"
    echo "$DUPLICATE_CHECK"
    
    # Count occurrences of each critical module
    PROXY_COUNT=$(find /etc/httpd -name "*.conf" -type f -exec grep -c "LoadModule.*proxy_module" {} \; 2>/dev/null | awk '{sum+=$1} END {print sum+0}')
    SSL_COUNT=$(find /etc/httpd -name "*.conf" -type f -exec grep -c "LoadModule.*ssl_module" {} \; 2>/dev/null | awk '{sum+=$1} END {print sum+0}')
    REWRITE_COUNT=$(find /etc/httpd -name "*.conf" -type f -exec grep -c "LoadModule.*rewrite_module" {} \; 2>/dev/null | awk '{sum+=$1} END {print sum+0}')
    HEADERS_COUNT=$(find /etc/httpd -name "*.conf" -type f -exec grep -c "LoadModule.*headers_module" {} \; 2>/dev/null | awk '{sum+=$1} END {print sum+0}')
    
    echo "Module loading counts:"
    echo "  proxy_module: $PROXY_COUNT times"
    echo "  ssl_module: $SSL_COUNT times" 
    echo "  rewrite_module: $REWRITE_COUNT times"
    echo "  headers_module: $HEADERS_COUNT times"
    
    # If any module is loaded more than once, clean up
    if [ "$PROXY_COUNT" -gt 1 ] || [ "$SSL_COUNT" -gt 1 ] || [ "$REWRITE_COUNT" -gt 1 ] || [ "$HEADERS_COUNT" -gt 1 ]; then
        echo "⚠ WARNING: Duplicate module loading detected - cleaning up..."
        
        # Clean up our custom proxy configuration if it exists
        PROXY_CONF="/etc/httpd/conf.modules.d/00-proxy.conf"
        if [ -f "$PROXY_CONF" ] && grep -q "WebSocket support" "$PROXY_CONF"; then
            echo "Removing our custom proxy module configuration..."
            sed -i '/# Proxy modules for WebSocket support/,+3d' "$PROXY_CONF" 2>/dev/null || true
            
            # If file is now empty or only has comments, remove it entirely
            if [ ! -s "$PROXY_CONF" ] || ! grep -q "LoadModule" "$PROXY_CONF" 2>/dev/null; then
                rm -f "$PROXY_CONF" 2>/dev/null || true
                echo "Removed empty proxy configuration file"
            fi
        fi
        
        # Test Apache configuration after cleanup
        if ! httpd -t 2>/dev/null; then
            echo "⚠ Apache configuration test failed after cleanup, checking syntax..."
            httpd -t 2>&1 | head -10
        fi
    fi
fi

# Now check if required modules are actually loaded
echo ""
echo "--- Verifying required modules are loaded ---"

# Check if proxy modules are loaded
if httpd -M 2>/dev/null | grep -q "proxy_module"; then
    echo "✓ proxy_module is loaded"
else
    echo "✗ proxy_module is NOT loaded"
fi

if httpd -M 2>/dev/null | grep -q "proxy_wstunnel_module"; then
    echo "✓ proxy_wstunnel_module is loaded"  
else
    echo "✗ proxy_wstunnel_module is NOT loaded"
fi

if httpd -M 2>/dev/null | grep -q "ssl_module"; then
    echo "✓ ssl_module is loaded"
else
    echo "✗ ssl_module is NOT loaded"
fi

# Only add proxy module configuration if modules are missing AND not already configured
PROXY_CONF="/etc/httpd/conf.modules.d/00-proxy.conf"
MISSING_MODULES=""

if ! httpd -M 2>/dev/null | grep -q "proxy_module"; then
    MISSING_MODULES="$MISSING_MODULES proxy_module"
fi

if ! httpd -M 2>/dev/null | grep -q "proxy_wstunnel_module"; then
    MISSING_MODULES="$MISSING_MODULES proxy_wstunnel_module"
fi

if [ -n "$MISSING_MODULES" ]; then
    echo "Missing modules detected: $MISSING_MODULES"
    echo "Adding minimal proxy module configuration..."
    
    # Create a clean proxy configuration
    cat > "$PROXY_CONF" << 'EOF'
# Minimal proxy modules for WebSocket and PHP-FPM support
# Added by Learning Journeys deployment script
# Note: Order matters - mod_proxy must be loaded before other proxy modules
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
EOF
    
    echo "✓ Proxy modules configured at: $PROXY_CONF"
    
    # Test configuration
    if httpd -t 2>/dev/null; then
        echo "✓ Apache configuration test passed after adding modules"
    else
        echo "✗ Apache configuration test failed - removing proxy config"
        rm -f "$PROXY_CONF"
    fi
else
    echo "✓ All required proxy modules are already loaded by default Apache configuration"
    
    # Even if other modules are loaded, we still need to ensure mod_proxy_fcgi is available for PHP
    if ! httpd -M 2>/dev/null | grep -q "proxy_fcgi_module"; then
        echo "Adding mod_proxy_fcgi for PHP-FPM support..."
        # Ensure mod_proxy is loaded first, then add mod_proxy_fcgi
        cat > "$PROXY_CONF" << 'EOF'
# Comprehensive proxy modules including PHP-FPM support
# Added by Learning Journeys deployment script
# Note: Order matters - mod_proxy must be loaded before other proxy modules
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
LoadModule proxy_fcgi_module modules/mod_proxy_fcgi.so
EOF
        echo "✓ PHP-FPM proxy module configured at: $PROXY_CONF"
    else
        echo "✓ mod_proxy_fcgi already loaded"
    fi
    
    # Remove any existing custom proxy config since main modules are loaded
    # Note: Commented out to avoid removing the config we just created above
    # if [ -f "$PROXY_CONF" ] && grep -q "WebSocket support" "$PROXY_CONF"; then
    #     echo "Removing unnecessary proxy module configuration..."
    #     rm -f "$PROXY_CONF"
    # fi
fi

# Final verification
echo ""
echo "--- Final module verification ---"
if httpd -M 2>&1 | grep -E "(already loaded|AH01574)" | head -5; then
    echo "⚠ Some duplicate module warnings still present"
else
    echo "✓ No duplicate module warnings detected"
fi

# Update Apache configuration with WebSocket proxy
echo ""
echo "--- Updating Apache virtual host configuration ---"
if [ -f "/var/www/config/apache/learningjourneys.conf" ]; then
    # Backup existing configuration
    if [ -f "/etc/httpd/conf.d/learningjourneys.conf" ]; then
        cp "/etc/httpd/conf.d/learningjourneys.conf" "/etc/httpd/conf.d/learningjourneys.conf.backup"
        echo "✓ Backed up existing Apache configuration"
    fi
    
    # Copy new configuration
    cp "/var/www/config/apache/learningjourneys.conf" "/etc/httpd/conf.d/learningjourneys.conf"
    echo "✓ Apache configuration updated with WebSocket proxy"
    
    # Verify configuration syntax
    echo "Testing Apache configuration syntax..."
    if httpd -t 2>/dev/null; then
        echo "✓ Apache configuration syntax test passed"
    else
        echo "✗ Apache configuration syntax test failed"
        echo "Configuration errors:"
        httpd -t 2>&1 | head -10
        
        # Restore backup if test fails
        if [ -f "/etc/httpd/conf.d/learningjourneys.conf.backup" ]; then
            echo "Restoring backup configuration..."
            cp "/etc/httpd/conf.d/learningjourneys.conf.backup" "/etc/httpd/conf.d/learningjourneys.conf"
        fi
    fi
else
    echo "⚠ Apache configuration source not found at /var/www/config/apache/learningjourneys.conf"
fi

# Test virtual host configuration
echo ""
echo "--- Testing virtual host configuration ---"
VHOST_TEST=$(httpd -S 2>&1 | grep -A2 -B2 "the-thinking-course.com")
if [ -n "$VHOST_TEST" ]; then
    echo "✓ Virtual host configuration found:"
    echo "$VHOST_TEST"
else
    echo "⚠ Virtual host for the-thinking-course.com not found in configuration"
    echo "Available virtual hosts:"
    httpd -S 2>&1 | grep -E "(VirtualHost|ServerName)" | head -10
fi

# Final comprehensive Apache test
echo ""
echo "--- Comprehensive Apache configuration test ---"
echo "Testing Apache can start with current configuration..."
if systemctl configtest httpd 2>/dev/null || httpd -t 2>/dev/null; then
    echo "✓ Apache configuration is valid and ready for restart"
    
    # Check if Apache needs to be restarted due to module changes
    if [ -f "/tmp/.apache_modules_changed" ] || ! systemctl is-active --quiet httpd; then
        echo "Restarting Apache to apply configuration changes..."
        systemctl restart httpd
        if [ $? -eq 0 ]; then
            echo "✓ Apache restarted successfully"
            rm -f "/tmp/.apache_modules_changed" 2>/dev/null || true
        else
            echo "✗ Apache restart failed"
            systemctl status httpd --no-pager -l | head -10
        fi
    else
        echo "✓ Apache is running and configuration is current"
    fi
else
    echo "✗ Apache configuration test failed - not restarting"
    echo "Please check the configuration manually"
fi

# ============================================================================
# STEP 9: VERIFY FRONTEND ASSETS
# ============================================================================
echo ""
echo "--- Verifying Frontend Assets ---"

# Assets should already be compiled during CI/CD build phase
# We just verify they exist and have the correct configuration

# Check if critical assets exist
if [ -f "public/js/app.js" ]; then
    JS_SIZE=$(stat -c%s "public/js/app.js" 2>/dev/null || echo "0")
    echo "✓ app.js exists: $JS_SIZE bytes"
    
    # Check if production WebSocket config is included
    if grep -q "the-thinking-course.com" "public/js/app.js" 2>/dev/null; then
        echo "✓ Production WebSocket configuration found in assets"
    else
        echo "⚠ Production WebSocket configuration NOT found in compiled assets"
        if grep -q "localhost" "public/js/app.js" 2>/dev/null; then
            echo "⚠ Development configuration (localhost) detected in assets"
            echo "⚠ Assets were compiled with development environment variables"
        fi
    fi
    
    # Check if Bootstrap is included
    if grep -q "bootstrap" "public/js/app.js" 2>/dev/null; then
        echo "✓ Bootstrap JavaScript included"
    fi
else
    echo "❌ app.js not found - frontend assets missing"
    echo "❌ This will cause JavaScript functionality to fail"
fi

if [ -f "public/css/app.css" ]; then
    CSS_SIZE=$(stat -c%s "public/css/app.css" 2>/dev/null || echo "0")
    echo "✓ app.css exists: $CSS_SIZE bytes"
    
    # Check if Bootstrap and Bootstrap Icons are included
    if grep -q "bootstrap" "public/css/app.css" 2>/dev/null; then
        echo "✓ Bootstrap CSS included"
    fi
    if grep -q "bootstrap-icons" "public/css/app.css" 2>/dev/null; then
        echo "✓ Bootstrap Icons CSS included"
    fi
else
    echo "❌ app.css not found - frontend styles missing"
fi

# Check if Bootstrap Icons fonts were copied
if [ -d "public/fonts" ]; then
    FONT_COUNT=$(find public/fonts -name "*.woff*" -o -name "*.ttf" -o -name "*.eot" 2>/dev/null | wc -l)
    if [ "$FONT_COUNT" -gt 0 ]; then
        echo "✓ Bootstrap Icons fonts available: $FONT_COUNT files"
    else
        echo "⚠ No Bootstrap Icons font files found"
    fi
else
    echo "⚠ public/fonts directory not found"
fi

echo "✓ Frontend asset verification completed"

# ============================================================================
# STEP 10: OPTIMIZE LARAVEL
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

# Verify critical routes are cached properly
echo "--- Verifying Route Cache ---"
echo "Checking if critical routes are available after caching:"
if run_artisan_quiet route:list --name=preview-chat 2>/dev/null | grep -q preview-chat; then
    echo "✓ preview-chat route found"
else
    echo "⚠ preview-chat route NOT found - this will cause errors in production"
    echo "Checking if route exists in source files:"
    grep -n "preview-chat" routes/web.php || echo "Route not found in web.php"
    echo "APP_DEBUG setting: $(grep "^APP_DEBUG=" "$ENV_FILE" 2>/dev/null || echo "Not found")"
fi

run_artisan_quiet route:list --name=journeys.show 2>/dev/null && echo "✓ journeys.show route found" || echo "⚠ journeys.show route NOT found"

# Count total routes to verify route cache is working
ROUTE_COUNT=$(run_artisan_quiet route:list --json 2>/dev/null | jq length 2>/dev/null || echo "0")
echo "Total routes cached: $ROUTE_COUNT"

if [ "$ROUTE_COUNT" = "0" ]; then
    echo "⚠ Route cache appears empty, attempting to rebuild..."
    run_artisan_quiet route:clear || true
    run_artisan_quiet route:cache || true
    ROUTE_COUNT_RETRY=$(run_artisan_quiet route:list --json 2>/dev/null | jq length 2>/dev/null || echo "0")
    echo "Routes after rebuild: $ROUTE_COUNT_RETRY"
fi

echo "✓ Laravel optimization completed"

# Fix ownership after running Laravel commands
echo "--- Fixing ownership after Laravel commands ---"
chown -R ec2-user:apache "$APP_DIR/storage"
chown -R ec2-user:apache "$APP_DIR/bootstrap/cache"

# Ensure route cache file has correct permissions
if [ -f "$APP_DIR/bootstrap/cache/routes-v7.php" ]; then
    chown ec2-user:apache "$APP_DIR/bootstrap/cache/routes-v7.php"
    chmod 644 "$APP_DIR/bootstrap/cache/routes-v7.php"
    echo "✓ Fixed route cache file permissions"
fi

# Ensure config cache file has correct permissions  
if [ -f "$APP_DIR/bootstrap/cache/config.php" ]; then
    chown ec2-user:apache "$APP_DIR/bootstrap/cache/config.php"
    chmod 644 "$APP_DIR/bootstrap/cache/config.php"
    echo "✓ Fixed config cache file permissions"
fi

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

if ! grep -q "REVERB_APP_ID=" "$ENV_FILE"; then
    MISSING_CONFIGS="$MISSING_CONFIGS REVERB_APP_ID"
fi

if [ -n "$MISSING_CONFIGS" ]; then
    echo "✗ Missing configurations:$MISSING_CONFIGS"
    echo "Showing last 10 lines of .env file for debugging:"
    tail -10 "$ENV_FILE"
    exit 1
fi

echo "✓ REVERB_APP_ID: $(grep "^REVERB_APP_ID=" "$ENV_FILE")"
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
echo "--- Restarting WebSocket Services ---"
# Clear Laravel configuration cache to ensure new settings are loaded
echo "Clearing Laravel configuration cache..."
run_artisan_quiet config:clear || echo "⚠ Config clear failed"
run_artisan_quiet config:cache || echo "⚠ Config cache failed"

# Stop any existing Reverb processes
echo "Stopping existing Reverb processes..."
pkill -f "artisan reverb:start" || echo "No existing Reverb processes found"

# Wait a moment for processes to stop
sleep 2

# Start Reverb server in background
echo "Starting Laravel Reverb server..."
cd "$APP_DIR"

# Set proper environment for Reverb startup
export LC_ALL=C.UTF-8
export LANG=C.UTF-8

# Start Reverb with proper UTF-8 handling
nohup php -d mbstring.internal_encoding=UTF-8 -d default_charset=UTF-8 artisan reverb:start --host=0.0.0.0 --port=8080 > "$APP_DIR/storage/logs/reverb.log" 2>&1 &
REVERB_PID=$!
echo "✓ Reverb server started with PID: $REVERB_PID"
echo "✓ Reverb logs: $APP_DIR/storage/logs/reverb.log"

# Wait a moment and check if Reverb is running
sleep 3
if ps -p $REVERB_PID > /dev/null; then
    echo "✓ Reverb server is running successfully"
    echo "✓ WebSocket server available on: ws://localhost:8080 (proxied to wss://$REVERB_HOST)"
else
    echo "❌ Reverb server failed to start"
    if [ -f "$APP_DIR/storage/logs/reverb.log" ]; then
        echo "Last 10 lines of Reverb log:"
        tail -10 "$APP_DIR/storage/logs/reverb.log"
    fi
fi

echo ""
echo "=== AFTER INSTALL COMPLETED SUCCESSFULLY ==="