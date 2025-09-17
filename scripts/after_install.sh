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
    php -d error_reporting="E_ALL & ~E_DEPRECATED & ~E_STRICT" "$@" 2>/dev/null || php "$@"
}

# Function to run artisan commands quietly
run_artisan_quiet() {
    run_php_quiet artisan "$@"
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

# Verify APP_KEY is set from secrets
if grep -q "APP_KEY=$" "$ENV_FILE" || ! grep -q "APP_KEY=" "$ENV_FILE"; then
    echo "⚠ APP_KEY not properly set from AWS Secrets Manager"
    echo "Current APP_KEY line: $(grep "APP_KEY=" "$ENV_FILE" || echo 'APP_KEY line not found')"
else
    echo "✓ APP_KEY successfully applied from AWS Secrets Manager"
fi

# ============================================================================
# STEP 4: TEST DATABASE CONNECTION
# ============================================================================
echo ""
echo "--- Testing database connection ---"

run_php_quiet -r "
try {
    // Read .env file manually instead of using parse_ini_file
    \$envFile = '.env';
    if (!file_exists(\$envFile)) {
        throw new Exception('.env file not found');
    }
    
    \$lines = file(\$envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    \$env = [];
    foreach (\$lines as \$line) {
        if (strpos(trim(\$line), '#') === 0) continue; // Skip comments
        if (strpos(\$line, '=') !== false) {
            list(\$key, \$value) = explode('=', \$line, 2);
            \$env[trim(\$key)] = trim(\$value, '\"\'');
        }
    }
    
    if (!class_exists('PDO')) {
        throw new Exception('PDO class not found - PDO extension not installed');
    }
    
    \$pdo = new PDO(
        \"mysql:host={\$env['DB_HOST']};dbname={\$env['DB_DATABASE']}\",
        \$env['DB_USERNAME'],
        \$env['DB_PASSWORD']
    );
    echo \"✓ Database connection successful\n\";
} catch (Exception \$e) {
    echo \"✗ Database connection failed: \" . \$e->getMessage() . \"\n\";
    exit(1);
}
"

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
# STEP 7: OPTIMIZE LARAVEL
# ============================================================================
echo ""
echo "--- Optimizing Laravel ---"

run_artisan_quiet config:clear || echo "⚠ Config clear failed"
run_artisan_quiet route:clear || echo "⚠ Route clear failed" 
run_artisan_quiet view:clear || echo "⚠ View clear failed"
run_artisan_quiet config:cache || echo "⚠ Config cache failed"
run_artisan_quiet route:cache || echo "⚠ Route cache failed"
run_artisan_quiet view:cache || echo "⚠ View cache failed"

echo "✓ Laravel optimization completed"

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
echo "=== AFTER INSTALL COMPLETED SUCCESSFULLY ==="