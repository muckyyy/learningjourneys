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

# ============================================================================
# STEP 1: CONFIGURE ENVIRONMENT (SIMPLIFIED VERSION)
# ============================================================================
echo ""
echo "--- Configuring environment ---"

# Check if .env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env file not found at $ENV_FILE"
    exit 1
fi

echo "✓ Found .env file"

# Create a backup of the original .env
cp .env .env.backup
echo "✓ Backed up original .env file"

# Update critical production settings in .env
echo "Updating production settings in .env..."

# Set production environment
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

echo "✓ Updated environment settings to production mode"

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

# Get database password from environment file
DB_PASSWORD=$(grep "^DB_PASSWORD=" $ENV_FILE | cut -d '=' -f2 | sed 's/^["'\'']*//;s/["'\'']*$//')

# Ensure MariaDB is running
systemctl start mariadb
systemctl enable mariadb

# Wait for MariaDB to be ready
echo "Waiting for MariaDB to be ready..."
sleep 5

# Initialize MariaDB if this is the first run
if ! mysql -u root -e "SELECT 1" &> /dev/null; then
    echo "Initializing MariaDB for first time..."
    mysql_secure_installation <<EOF

y
$DB_PASSWORD
$DB_PASSWORD
y
y
y
y
EOF
    echo "✓ MariaDB initialized"
fi

# Try to connect and setup database
echo "Setting up database..."
if mysql -u root -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null; then
    echo "✓ Connected to MariaDB with existing password"
elif mysql -u root -e "SELECT 1" &> /dev/null; then
    # If we can connect without password, set the password
    echo "Setting MariaDB root password..."
    mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED BY '$DB_PASSWORD'; FLUSH PRIVILEGES;" || true
    echo "✓ MariaDB root password set"
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