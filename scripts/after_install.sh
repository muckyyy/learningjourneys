#!/bin/bash
set -e

echo "=== AFTER INSTALL PHASE ==="
echo "Time: $(date)"

APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

echo "=== DEPLOYMENT VERIFICATION ==="
echo "Contents of /var/www after file copy:"
ls -la /var/www/ 2>/dev/null || echo "Cannot access /var/www"
echo ""
echo "Total file count: $(find /var/www -type f | wc -l)"
echo "Total directory count: $(find /var/www -type d | wc -l)"
echo ""
echo "Checking for critical files/directories:"
echo "- artisan file: $([ -f /var/www/artisan ] && echo "✓ Present" || echo "✗ Missing")"
echo "- app directory: $([ -d /var/www/app ] && echo "✓ Present" || echo "✗ Missing")"
echo "- scripts directory: $([ -d /var/www/scripts ] && echo "✓ Present" || echo "✗ Missing")"
echo "- config directory: $([ -d /var/www/config ] && echo "✓ Present" || echo "✗ Missing")"
echo "- public directory: $([ -d /var/www/public ] && echo "✓ Present" || echo "✗ Missing")"
echo "- composer.json: $([ -f /var/www/composer.json ] && echo "✓ Present" || echo "✗ Missing")"
echo ""

if [ ! -f /var/www/artisan ] || [ ! -d /var/www/app ]; then
    echo "CRITICAL ERROR: Laravel application files are missing!"
    echo "This indicates a problem with the CodeBuild artifact or CodeDeploy file copy."
    echo "Available files:"
    find /var/www -type f | head -20
    echo "Available directories:" 
    find /var/www -type d | head -10
fi

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
MAX_WAIT=30

while [ $WAIT_COUNT -lt $MAX_WAIT ]; do
    if [ -f "artisan" ] && [ -f "composer.json" ] && [ -d "app" ]; then
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
echo "--- Configuring environment (fresh deployment) ---"

# Always create fresh .env file from template for clean deployment
echo "Creating fresh .env file for deployment..."

# Remove any existing .env files
if [ -f "$ENV_FILE" ]; then
    echo "Removing existing .env file..."
    rm -f "$ENV_FILE"
fi

# Create new .env file from .env.example if available
if [ -f "$APP_DIR/.env.example" ]; then
    echo "Creating fresh .env from .env.example template..."
    cp "$APP_DIR/.env.example" "$ENV_FILE"
    
    # Add a small delay to ensure file is written
    sleep 2
    
    echo "✓ Created fresh .env file from .env.example"
    
    # Show current .env content for verification
    echo "Current .env file contents (first 10 lines):"
    head -10 "$ENV_FILE" || echo "Could not read .env file"
    
else
    echo "ERROR: .env.example template file not found!"
    echo "The .env.example file is required to create the environment configuration."
    echo "Please ensure .env.example is included in your deployment files."
    exit 1
fi

# Verify the fresh .env file was created
if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: Failed to create .env file from template"
    exit 1
fi

echo "✓ Fresh .env file created successfully from .env.example"

# Add pause before AWS Secrets Manager integration
echo "Waiting 3 seconds before applying AWS Secrets Manager values..."
sleep 3
echo "Starting environment configuration with AWS Secrets Manager values..."

# Check AWS CLI and credentials
echo "Checking AWS CLI configuration..."
echo "Current AWS identity:"
if ! AWS_IDENTITY=$(aws sts get-caller-identity 2>&1); then
    echo "ERROR: AWS credentials not configured or not working"
    echo "AWS Error Details: $AWS_IDENTITY"
    echo "This could be due to:"
    echo "1. No IAM role attached to EC2 instance"
    echo "2. IAM role lacks secretsmanager:GetSecretValue permission"
    echo "3. AWS CLI not properly configured"
    exit 1
fi

echo "✓ AWS CLI working - Identity: $AWS_IDENTITY"

# Check if the secret exists and we have access to it
echo "Checking access to AWS Secrets Manager..."
if ! aws secretsmanager describe-secret --secret-id "learningjourneys/keys" --region "eu-west-1" >/dev/null 2>&1; then
    echo "ERROR: Cannot access secret 'learningjourneys/keys' in eu-west-1"
    echo "This could be due to:"
    echo "1. Secret doesn't exist"
    echo "2. Wrong region (should be eu-west-1)"
    echo "3. IAM role lacks secretsmanager:DescribeSecret permission"
    echo "4. Secret name is different"
    exit 1
fi

echo "✓ Secret 'learningjourneys/keys' is accessible"

# Fetch secrets from AWS Secrets Manager
echo "Fetching secrets from AWS Secrets Manager..."
if ! SECRET_JSON=$(aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text 2>&1); then
    echo "ERROR: Failed to fetch secrets from AWS Secrets Manager"
    echo "AWS Error: $SECRET_JSON"
    echo "Raw command that failed:"
    echo "aws secretsmanager get-secret-value --secret-id 'learningjourneys/keys' --region 'eu-west-1' --query SecretString --output text"
    exit 1
fi

echo "✓ Successfully fetched secrets from AWS Secrets Manager"
echo "Secret JSON length: ${#SECRET_JSON} characters"

# Check if jq is available
if ! command -v jq &> /dev/null; then
    echo "ERROR: jq is not installed"
    exit 1
fi

# Parse secrets from JSON with validation
echo "Parsing secrets from JSON..."

# Check if jq is available
if ! command -v jq &> /dev/null; then
    echo "ERROR: jq is not installed - required for JSON parsing"
    echo "Installing jq..."
    dnf install -y jq
    if [ $? -ne 0 ]; then
        echo "ERROR: Failed to install jq"
        exit 1
    fi
fi

# Validate that we got valid JSON
if ! echo "$SECRET_JSON" | jq . >/dev/null 2>&1; then
    echo "ERROR: Invalid JSON received from AWS Secrets Manager"
    echo "Raw secret data (first 200 chars): ${SECRET_JSON:0:200}"
    exit 1
fi

# Extract specific values with error checking
if ! DB_HOST=$(echo "$SECRET_JSON" | jq -r '.DB_HOST' 2>&1); then
    echo "ERROR: Failed to extract DB_HOST from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if ! DB_USER=$(echo "$SECRET_JSON" | jq -r '.DB_USER' 2>&1); then
    echo "ERROR: Failed to extract DB_USER from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if ! DB_DATABASE=$(echo "$SECRET_JSON" | jq -r '.DB_DATABASE' 2>&1); then
    echo "ERROR: Failed to extract DB_DATABASE from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if ! DB_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.DB_PASSWORD' 2>&1); then
    echo "ERROR: Failed to extract DB_PASSWORD from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if ! OPENAI_API_KEY=$(echo "$SECRET_JSON" | jq -r '.OPENAI_API_KEY' 2>&1); then
    echo "ERROR: Failed to extract OPENAI_API_KEY from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if ! APP_URL=$(echo "$SECRET_JSON" | jq -r '.APP_URL' 2>&1); then
    echo "ERROR: Failed to extract APP_URL from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if ! DB_CONNECTION=$(echo "$SECRET_JSON" | jq -r '.DB_CONNECTION' 2>&1); then
    echo "ERROR: Failed to extract DB_CONNECTION from secret JSON"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

# Validate that we got actual values (not "null")
if [ "$DB_HOST" = "null" ] || [ "$DB_HOST" = "" ]; then
    echo "ERROR: DB_HOST is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if [ "$DB_USER" = "null" ] || [ "$DB_USER" = "" ]; then
    echo "ERROR: DB_USER is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if [ "$DB_DATABASE" = "null" ] || [ "$DB_DATABASE" = "" ]; then
    echo "ERROR: DB_DATABASE is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if [ "$DB_PASSWORD" = "null" ] || [ "$DB_PASSWORD" = "" ]; then
    echo "ERROR: DB_PASSWORD is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if [ "$OPENAI_API_KEY" = "null" ] || [ "$OPENAI_API_KEY" = "" ]; then
    echo "ERROR: OPENAI_API_KEY is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if [ "$APP_URL" = "null" ] || [ "$APP_URL" = "" ]; then
    echo "ERROR: APP_URL is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

if [ "$DB_CONNECTION" = "null" ] || [ "$DB_CONNECTION" = "" ]; then
    echo "ERROR: DB_CONNECTION is null or empty in the secret"
    echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
    exit 1
fi

echo "✓ Successfully parsed secrets:"
echo "  - DB_HOST: $DB_HOST"
echo "  - DB_USER: $DB_USER" 
echo "  - DB_DATABASE: $DB_DATABASE"
echo "  - DB_PASSWORD length: ${#DB_PASSWORD}"
echo "  - OPENAI_API_KEY length: ${#OPENAI_API_KEY}"
echo "  - APP_URL: $APP_URL"
echo "  - DB_CONNECTION: $DB_CONNECTION"

# Update critical production settings in .env
echo "Updating production settings in .env..."

# Set production environment
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_ENV=testing/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

# Update APP_URL from AWS Secrets Manager
echo "Updating APP_URL to: $APP_URL"
sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env

# Update DB_CONNECTION from AWS Secrets Manager  
echo "Updating DB_CONNECTION to: $DB_CONNECTION"
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=$DB_CONNECTION/" .env

# Set database configuration for production
sed -i "s/DB_HOST=127.0.0.1/DB_HOST=$DB_HOST/" .env
sed -i "s/DB_HOST=localhost/DB_HOST=$DB_HOST/" .env
sed -i "s/DB_DATABASE=laravel/DB_DATABASE=$DB_DATABASE/" .env
sed -i "s/DB_DATABASE=learningjourneys/DB_DATABASE=$DB_DATABASE/" .env
sed -i "s/DB_USERNAME=root/DB_USERNAME=$DB_USER/" .env

# Update database password from AWS Secrets Manager
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=\"$DB_PASSWORD\"/" .env

# Update OpenAI API key from AWS Secrets Manager
if grep -q "^OPENAI_API_KEY=" .env; then
    sed -i "s/^OPENAI_API_KEY=.*/OPENAI_API_KEY=$OPENAI_API_KEY/" .env
else
    echo "OPENAI_API_KEY=$OPENAI_API_KEY" >> .env
fi

# Verify the secrets were actually written to .env file
echo "Verifying secrets were written to .env file..."
if ! grep -q "^DB_PASSWORD=" .env; then
    echo "ERROR: DB_PASSWORD line not found in .env file"
    exit 1
fi

if ! grep -q "^OPENAI_API_KEY=" .env; then
    echo "ERROR: OPENAI_API_KEY line not found in .env file"
    exit 1
fi

if ! grep -q "^APP_URL=" .env; then
    echo "ERROR: APP_URL line not found in .env file"
    exit 1
fi

if ! grep -q "^DB_CONNECTION=" .env; then
    echo "ERROR: DB_CONNECTION line not found in .env file"
    exit 1
fi

# Check that the values are not empty (without showing the actual secrets)
DB_HOST_CHECK=$(grep "^DB_HOST=" .env | cut -d'=' -f2)
DB_USER_CHECK=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2)
DB_DATABASE_CHECK=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2)
DB_PASSWORD_CHECK=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"')
OPENAI_API_KEY_CHECK=$(grep "^OPENAI_API_KEY=" .env | cut -d'=' -f2 | tr -d '"')
APP_URL_CHECK=$(grep "^APP_URL=" .env | cut -d'=' -f2)
DB_CONNECTION_CHECK=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2)

if [ -z "$DB_HOST_CHECK" ]; then
    echo "ERROR: DB_HOST is empty in .env file after update"
    echo "DB_HOST line in .env: $(grep "^DB_HOST=" .env)"
    exit 1
fi

if [ -z "$DB_USER_CHECK" ]; then
    echo "ERROR: DB_USERNAME is empty in .env file after update"
    echo "DB_USERNAME line in .env: $(grep "^DB_USERNAME=" .env)"
    exit 1
fi

if [ -z "$DB_DATABASE_CHECK" ]; then
    echo "ERROR: DB_DATABASE is empty in .env file after update"
    echo "DB_DATABASE line in .env: $(grep "^DB_DATABASE=" .env)"
    exit 1
fi

if [ -z "$DB_PASSWORD_CHECK" ]; then
    echo "ERROR: DB_PASSWORD is empty in .env file after update"
    echo "DB_PASSWORD line in .env: $(grep "^DB_PASSWORD=" .env)"
    exit 1
fi

if [ -z "$OPENAI_API_KEY_CHECK" ]; then
    echo "ERROR: OPENAI_API_KEY is empty in .env file after update"
    echo "OPENAI_API_KEY line in .env: $(grep "^OPENAI_API_KEY=" .env | cut -c1-30)..."
    exit 1
fi

if [ -z "$APP_URL_CHECK" ]; then
    echo "ERROR: APP_URL is empty in .env file after update"
    echo "APP_URL line in .env: $(grep "^APP_URL=" .env)"
    exit 1
fi

if [ -z "$DB_CONNECTION_CHECK" ]; then
    echo "ERROR: DB_CONNECTION is empty in .env file after update"
    echo "DB_CONNECTION line in .env: $(grep "^DB_CONNECTION=" .env)"
    exit 1
fi

echo "✓ Successfully updated and verified .env file with secrets"
echo "DB_HOST: $DB_HOST_CHECK"
echo "DB_USERNAME: $DB_USER_CHECK"
echo "DB_DATABASE: $DB_DATABASE_CHECK"
echo "DB_PASSWORD length in .env: ${#DB_PASSWORD_CHECK}"
echo "OPENAI_API_KEY length in .env: ${#OPENAI_API_KEY_CHECK}"
echo "APP_URL: $APP_URL_CHECK"
echo "DB_CONNECTION: $DB_CONNECTION_CHECK"

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

# ============================================================================
# STEP 2: UPDATE APACHE CONFIGURATION WITH APP_URL
# ============================================================================
echo ""
echo "--- Updating Apache configuration with APP_URL from secrets ---"

# Extract domain from APP_URL (remove http:// or https://)
DOMAIN=$(echo "$APP_URL" | sed -E 's|^https?://||' | sed 's|/.*$||')
echo "Extracted domain from APP_URL: $DOMAIN"

# Update Apache configuration file
APACHE_CONF="/var/www/config/apache/learningjourneys.conf"
if [ -f "$APACHE_CONF" ]; then
    echo "Updating Apache configuration file: $APACHE_CONF"
    
    # Create backup of original configuration
    cp "$APACHE_CONF" "$APACHE_CONF.backup.$(date +%s)"
    
    # Update ServerName and ServerAlias with the domain from APP_URL
    sed -i "s/ServerName .*/ServerName $DOMAIN/" "$APACHE_CONF"
    sed -i "s/ServerAlias .*/ServerAlias www.$DOMAIN/" "$APACHE_CONF"
    
    echo "✓ Updated Apache configuration:"
    echo "  ServerName: $DOMAIN"
    echo "  ServerAlias: www.$DOMAIN"
    
    # Verify the changes were applied
    if grep -q "ServerName $DOMAIN" "$APACHE_CONF"; then
        echo "✓ ServerName successfully updated in Apache configuration"
    else
        echo "⚠ Warning: ServerName update may have failed"
    fi
else
    echo "⚠ Apache configuration file not found: $APACHE_CONF"
    echo "Available config files:"
    find /var/www -name "*.conf" -type f 2>/dev/null || echo "No .conf files found"
fi

# ============================================================================
# STEP 3: INSTALL CERTBOT AND MANAGE SSL CERTIFICATES
# ============================================================================
echo ""
echo "--- Installing Certbot and managing SSL certificates ---"

# Install Certbot if not already installed (optional - won't fail deployment)
if ! command -v certbot &> /dev/null; then
    echo "Installing Certbot for Let's Encrypt SSL certificates..."
    
    # Try the simplest method first - using system packages
    echo "Attempting to install certbot via system packages..."
    if dnf install -y certbot python3-certbot-apache 2>/dev/null; then
        echo "✓ Certbot installed successfully via system packages"
    else
        echo "System package installation failed, trying pip3 with build dependencies..."
        
        # Install build dependencies required for pip3 packages
        if dnf groupinstall -y "Development Tools" && dnf install -y python3 python3-pip python3-devel augeas-devel gcc; then
            echo "Build dependencies installed, attempting pip3 install..."
            
            # Install certbot and apache plugin via pip3
            if pip3 install certbot certbot-apache; then
                echo "✓ Certbot installed successfully via pip3"
                
                # Ensure certbot is in PATH
                if [ ! -e /usr/local/bin/certbot ]; then
                    # Find where pip3 installed certbot and create symlink
                    CERTBOT_PATH=$(find /usr/local/bin /home/ec2-user/.local/bin /root/.local/bin -name "certbot" -type f 2>/dev/null | head -1)
                    if [ -n "$CERTBOT_PATH" ] && [ -f "$CERTBOT_PATH" ]; then
                        ln -s "$CERTBOT_PATH" /usr/local/bin/certbot 2>/dev/null || true
                        export PATH="/usr/local/bin:$PATH"
                    fi
                fi
            else
                echo "⚠ pip3 installation also failed"
            fi
        else
            echo "⚠ Failed to install build dependencies"
        fi
    fi
    
    # Final check
    if command -v certbot &> /dev/null; then
        echo "✓ Certbot is now available"
        certbot --version
    else
        echo "⚠ Warning: Certbot installation failed completely"
        echo "Deployment will continue without SSL certificate setup"
        echo "You can manually install certbot later using:"
        echo "  sudo dnf groupinstall 'Development Tools'"
        echo "  sudo dnf install python3-pip python3-devel augeas-devel gcc"
        echo "  sudo pip3 install certbot certbot-apache"
        echo "Skipping SSL certificate management..."
        SKIP_SSL="true"
    fi
else
    echo "✓ Certbot already installed"
    certbot --version
fi

# Only proceed with certificate management if certbot is available and SSL isn't skipped
if [ "$SKIP_SSL" != "true" ] && command -v certbot &> /dev/null; then
    echo "Managing SSL certificate for domain: $DOMAIN"
    
    # Check if certificate already exists
    if certbot certificates 2>/dev/null | grep -q "$DOMAIN"; then
        echo "Certificate already exists for $DOMAIN, attempting renewal..."
        
        # Attempt certificate renewal
        if certbot renew --quiet --no-self-upgrade; then
            echo "✓ SSL certificate renewed successfully"
        else
            echo "⚠ Certificate renewal failed, but continuing deployment..."
        fi
    else
        echo "No existing certificate found for $DOMAIN, obtaining new certificate..."
        
        # Ensure Apache is running for webroot authentication
        systemctl start httpd || true
        sleep 3
        
        # Obtain new certificate using Apache plugin (non-interactive)
        if certbot --apache \
           --non-interactive \
           --agree-tos \
           --redirect \
           --hsts \
           --staple-ocsp \
           --email admin@${DOMAIN} \
           -d ${DOMAIN} \
           -d www.${DOMAIN}; then
            echo "✓ SSL certificate obtained and configured successfully"
            echo "✓ Certbot automatically configured Apache with SSL settings"
        else
            echo "⚠ Failed to obtain SSL certificate"
            echo "Possible reasons:"
            echo "- Domain $DOMAIN may not be pointing to this server"
            echo "- Port 80 may not be accessible from the internet"
            echo "- Apache may not be configured properly"
            echo "Continuing with HTTP-only configuration..."
        fi
    fi
    
    # Set up automatic renewal cron job if not already present
    if ! crontab -l 2>/dev/null | grep -q "certbot renew"; then
        echo "Setting up automatic SSL certificate renewal..."
        (crontab -l 2>/dev/null; echo "0 12 * * * /usr/bin/certbot renew --quiet") | crontab -
        echo "✓ Automatic renewal cron job added (runs daily at 12:00 PM)"
    else
        echo "✓ Automatic renewal cron job already configured"
    fi
else
    if [ "$SKIP_SSL" = "true" ]; then
        echo "⚠ SSL certificate management skipped due to Certbot installation failure"
        echo "  Your site will run on HTTP only for now"
        echo "  You can set up SSL manually later after installing Certbot"
    else
        echo "⚠ Certbot not available, skipping SSL certificate management"
    fi
fi

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
# STEP 2: TEST DATABASE CONNECTION
# ============================================================================
echo ""
echo "--- Testing database connection ---"

echo "Testing connection to external database at $DB_HOST..."

# Test database connectivity
if php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database connection successful';" 2>/dev/null; then
    echo "✓ Successfully connected to external database"
else
    echo "⚠ Database connection test failed - will attempt migrations anyway"
    echo "This could be due to:"
    echo "1. Database server not accessible from this instance"
    echo "2. Incorrect database credentials" 
    echo "3. Security group/firewall blocking database port 3306"
    echo "4. Database not yet fully initialized"
fi

# Run Laravel migrations
echo "Running database migrations..."
if php artisan migrate --force; then
    echo "✓ Database migrations completed successfully"
else
    echo "⚠ Database migrations failed - this may cause application issues"
    echo "Please verify:"
    echo "1. External database server is running and accessible"
    echo "2. Database '$DB_DATABASE' exists on the external server"
    echo "3. User 'root' has proper permissions on the external database"
fi

echo "✓ Database connection setup completed"

# ============================================================================
# STEP 3: SET PERMISSIONS
# ============================================================================
echo ""
echo "--- Setting file permissions ---"

# Set ownership
echo "Setting ownership to ec2-user:apache (Amazon Linux 2023 compatible)..."
chown -R ec2-user:apache $APP_DIR

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
    chown ec2-user:apache $APP_DIR/.env
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
echo "=== AFTER INSTALL PHASE COMPLETED ==="#   F o r c e   d e p l o y m e n t   u p d a t e   0 9 / 1 7 / 2 0 2 5   2 1 : 2 4 : 2 7  
 