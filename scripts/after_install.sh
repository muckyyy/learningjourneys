#!/bin/bash
set -e

echo "=== AFTER INSTALL PHASE ==="
echo "Time: $(date)"

APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

# Check if we're in the right directory
if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: Application directory $APP_DIR does not exist"
    exit 1
fi

cd $APP_DIR
echo "Working in directory: $(pwd)"

# Wait for file deployment to complete - check for key Laravel files
echo "Waiting for application files to be fully deployed..."
WAIT_COUNT=0
MAX_WAIT=60

while [ $WAIT_COUNT -lt $MAX_WAIT ]; do
    if [ -f "artisan" ] && [ -f "composer.json" ] && [ -f ".env.example" ]; then
        echo "✓ Core application files detected"
        break
    fi
    echo "Waiting for files to be deployed... ($((WAIT_COUNT + 1))/$MAX_WAIT)"
    sleep 2
    WAIT_COUNT=$((WAIT_COUNT + 1))
done

if [ $WAIT_COUNT -eq $MAX_WAIT ]; then
    echo "ERROR: Timeout waiting for application files to be deployed"
    echo "Current directory contents:"
    ls -la
    exit 1
fi

# Additional wait to ensure all files are written
echo "Ensuring all files are fully written..."
sleep 3

# ============================================================================
# STEP 1: CONFIGURE ENVIRONMENT WITH AWS SECRETS MANAGER
# ============================================================================
echo ""
echo "--- Configuring environment ---"

# Debug: Show what files are currently available
echo "Current files in /var/www:"
ls -la | head -10

# Check if .env file exists, if not create from .env.example
if [ -f "$ENV_FILE" ]; then
    echo "✓ Found existing .env file"
    cp .env .env.backup
    echo "✓ Backed up original .env file"
elif [ -f "$APP_DIR/.env.example" ]; then
    echo ".env file not found, creating from .env.example..."
    cp "$APP_DIR/.env.example" "$ENV_FILE"
    echo "✓ Created .env file from .env.example"
else
    echo "ERROR: Neither .env nor .env.example found"
    echo "Available files:"
    ls -la | grep -E "\.env|artisan|composer"
    exit 1
fi

# Check AWS CLI and credentials
echo "Checking AWS CLI configuration..."
if ! aws sts get-caller-identity; then
    echo "ERROR: AWS credentials not configured or not working"
    exit 1
fi

# Fetch secrets from AWS Secrets Manager
echo "Fetching secrets from AWS Secrets Manager..."
if ! SECRET_JSON=$(aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text 2>&1); then
    echo "ERROR: Failed to fetch secrets from AWS Secrets Manager"
    echo "AWS Error: $SECRET_JSON"
    exit 1
fi

echo "✓ Successfully fetched secrets from AWS Secrets Manager"

# Check if jq is available
if ! command -v jq &> /dev/null; then
    echo "ERROR: jq is not installed"
    exit 1
fi

# Extract individual secrets
echo "Extracting secrets from JSON..."
if ! DB_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.DB_PASSWORD' 2>&1); then
    echo "ERROR: Failed to extract DB_PASSWORD from secrets"
    echo "Secret JSON: $SECRET_JSON"
    exit 1
fi

if ! OPENAI_API_KEY=$(echo "$SECRET_JSON" | jq -r '.OPENAI_API_KEY' 2>&1); then
    echo "ERROR: Failed to extract OPENAI_API_KEY from secrets"
    exit 1
fi

# Validate secrets are not null or empty
if [ "$DB_PASSWORD" = "null" ] || [ -z "$DB_PASSWORD" ]; then
    echo "ERROR: DB_PASSWORD is null or empty in AWS Secrets Manager"
    exit 1
fi

if [ "$OPENAI_API_KEY" = "null" ] || [ -z "$OPENAI_API_KEY" ]; then
    echo "ERROR: OPENAI_API_KEY is null or empty in AWS Secrets Manager"
    exit 1
fi

echo "✓ Successfully extracted secrets"

# Update critical production settings in .env
echo "Updating production settings in .env..."

# Set production environment
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_ENV=testing/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

# Set database configuration for production
sed -i 's/DB_HOST=127.0.0.1/DB_HOST=localhost/' .env
sed -i 's/DB_DATABASE=laravel/DB_DATABASE=learningjourneys/' .env
sed -i 's/DB_USERNAME=root/DB_USERNAME=root/' .env

# Update database password from AWS Secrets Manager
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=\"$DB_PASSWORD\"/" .env

# Update OpenAI API key from AWS Secrets Manager
if grep -q "^OPENAI_API_KEY=" .env; then
    sed -i "s/^OPENAI_API_KEY=.*/OPENAI_API_KEY=$OPENAI_API_KEY/" .env
else
    echo "OPENAI_API_KEY=$OPENAI_API_KEY" >> .env
fi

# Set other OpenAI configuration if not present
if ! grep -q "^OPENAI_ORGANIZATION=" .env; then
    echo "OPENAI_ORGANIZATION=" >> .env
fi
if ! grep -q "^OPENAI_DEFAULT_MODEL=" .env; then
    echo "OPENAI_DEFAULT_MODEL=gpt-4o" >> .env
fi
if ! grep -q "^OPENAI_TEMPERATURE=" .env; then
    echo "OPENAI_TEMPERATURE=0.7" >> .env
fi
if ! grep -q "^OPENAI_VERIFY_SSL=" .env; then
    echo "OPENAI_VERIFY_SSL=false" >> .env
fi

echo "✓ Updated environment settings to production mode with AWS Secrets"

# Install composer dependencies if vendor directory doesn't exist or is incomplete
if [ ! -d "vendor" ] || [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    if command -v composer &> /dev/null; then
        composer install --no-dev --optimize-autoloader
        echo "✓ Composer dependencies installed"
    else
        echo "⚠ Composer not found, skipping dependency installation"
    fi
else
    echo "✓ Composer dependencies already installed"
fi

# Generate application key if not set
if grep -q "APP_KEY=$" .env || grep -q "APP_KEY=base64:$" .env; then
    echo "Generating new application key..."
    php artisan key:generate --force
    echo "✓ Application key generated"
else
    echo "✓ Application key already set"
fi

echo "✓ Environment configuration completed"

# ============================================================================
# STEP 2: SETUP DATABASE
# ============================================================================
echo ""
echo "--- Setting up database ---"

# Database password was already extracted from AWS Secrets Manager above
echo "Using database password from AWS Secrets Manager"

# Ensure MariaDB is running
systemctl start mariadb
systemctl enable mariadb

# Wait for MariaDB to be ready
echo "Waiting for MariaDB to be ready..."
sleep 5

# Initialize MariaDB if this is the first run
if ! mysql -u root -e "SELECT 1" &> /dev/null; then
    echo "Initializing MariaDB for first time..."
    # Set root password
    mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; FLUSH PRIVILEGES;" || {
        echo "Failed to set root password directly, trying mysql_secure_installation approach..."
        mysqladmin -u root password "$DB_PASSWORD" || echo "⚠ Password setting failed, continuing"
    }
    echo "✓ MariaDB initialized"
fi

# Try to connect and setup database
echo "Setting up database..."
if mysql -u root -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
    echo "✓ Connected to MariaDB with AWS Secrets Manager password"
elif mysql -u root -e "SELECT 1" &> /dev/null; then
    # If we can connect without password, set the password
    echo "Setting MariaDB root password from AWS Secrets..."
    mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; FLUSH PRIVILEGES;" || true
    echo "✓ MariaDB root password set from AWS Secrets"
else
    echo "⚠ Cannot connect to MariaDB, continuing anyway"
fi

# Create database if it doesn't exist
echo "Creating database if not exists..."
mysql -u root -p"$DB_PASSWORD" -e "CREATE DATABASE IF NOT EXISTS learningjourneys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || echo "⚠ Database creation failed, continuing"

# Run Laravel migrations
echo "Running database migrations..."
php artisan migrate --force || echo "⚠ Migrations failed, continuing"

echo "✓ Database setup completed"

# ============================================================================
# STEP 3: SET PERMISSIONS
# ============================================================================
echo ""
echo "--- Setting file permissions ---"

# Set ownership
echo "Setting ownership to apache:apache..."
chown -R apache:apache $APP_DIR

# Set base permissions
echo "Setting base file and directory permissions..."
find $APP_DIR -type f -exec chmod 644 {} \;
find $APP_DIR -type d -exec chmod 755 {} \;

# Set executable permissions for artisan and scripts
echo "Setting executable permissions..."
chmod +x $APP_DIR/artisan
if [ -d "$APP_DIR/scripts" ]; then
    chmod +x $APP_DIR/scripts/*.sh
fi

# Set writable permissions for Laravel directories
echo "Setting writable permissions for Laravel directories..."
chmod -R 775 $APP_DIR/storage
chmod -R 775 $APP_DIR/bootstrap/cache

# Ensure proper permissions for logs
if [ -d "$APP_DIR/storage/logs" ]; then
    chmod -R 775 $APP_DIR/storage/logs
fi

# Set secure permissions for .env file
if [ -f "$APP_DIR/.env" ]; then
    echo "Setting secure permissions for .env file..."
    chmod 600 $APP_DIR/.env
    chown apache:apache $APP_DIR/.env
fi

echo "✓ File permissions set"

# ============================================================================
# STEP 4: CLEAR AND CACHE LARAVEL CONFIGURATION
# ============================================================================
echo ""
echo "--- Optimizing Laravel caches ---"

# Clear and cache configuration
echo "Clearing and caching Laravel configuration..."
php artisan config:clear || echo "⚠ Config clear failed"
php artisan cache:clear || echo "⚠ Cache clear failed"
php artisan route:clear || echo "⚠ Route clear failed"
php artisan view:clear || echo "⚠ View clear failed"

# Cache configuration for production
php artisan config:cache || echo "⚠ Config cache failed"
php artisan route:cache || echo "⚠ Route cache failed"
php artisan view:cache || echo "⚠ View cache failed"

echo "✓ Laravel caches optimized"

echo ""
echo "=== AFTER INSTALL PHASE COMPLETED ==="