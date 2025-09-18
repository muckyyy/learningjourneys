#!/bin/bash
set -e

echo "=== QUICK FIX SCRIPT FOR LEARNINGJOURNEYS ==="
echo "Time: $(date)"

APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

# Check if we're running as root/sudo
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: This script must be run with sudo"
    echo "Usage: sudo ./quick_fix.sh"
    exit 1
fi

echo "Working in directory: $APP_DIR"
cd $APP_DIR

echo ""
echo "=== STEP 1: FIX FILE OWNERSHIP ==="

# Fix ownership to ec2-user:apache (Amazon Linux 2023 compatible)
echo "Setting ownership to ec2-user:apache..."
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

echo "✓ File ownership and permissions fixed"

echo ""
echo "=== STEP 2: RECREATE .ENV FROM TEMPLATE ==="

# Backup existing .env if it exists
if [ -f "$ENV_FILE" ]; then
    echo "Backing up existing .env file..."
    cp "$ENV_FILE" "$ENV_FILE.backup.$(date +%s)"
    echo "✓ Backup created: $ENV_FILE.backup.$(date +%s)"
fi

# Remove existing .env
if [ -f "$ENV_FILE" ]; then
    echo "Removing existing .env file..."
    rm -f "$ENV_FILE"
fi

# Create fresh .env from .env.example
if [ -f "$APP_DIR/.env.example" ]; then
    echo "Creating fresh .env from .env.example template..."
    cp "$APP_DIR/.env.example" "$ENV_FILE"
    
    echo "✓ Created fresh .env file from .env.example"
    
    # Set secure permissions for .env file
    chmod 600 "$ENV_FILE"
    chown ec2-user:apache "$ENV_FILE"
    
    echo "✓ Set secure permissions for .env file"
    
    # Show current .env content for verification (first 10 lines)
    echo ""
    echo "New .env file contents (first 10 lines):"
    head -10 "$ENV_FILE"
else
    echo "ERROR: .env.example template file not found!"
    echo "Cannot recreate .env file without template."
    exit 1
fi

echo ""
echo "=== STEP 3: APPLY AWS SECRETS MANAGER VALUES ==="

# Check if jq is available
if ! command -v jq &> /dev/null; then
    echo "Installing jq for JSON parsing..."
    dnf install -y jq
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

# Parse secrets from JSON
DB_HOST=$(echo "$SECRET_JSON" | jq -r '.DB_HOST')
DB_USERNAME=$(echo "$SECRET_JSON" | jq -r '.DB_USERNAME')
DB_DATABASE=$(echo "$SECRET_JSON" | jq -r '.DB_DATABASE')
DB_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.DB_PASSWORD')
OPENAI_API_KEY=$(echo "$SECRET_JSON" | jq -r '.OPENAI_API_KEY')
APP_URL=$(echo "$SECRET_JSON" | jq -r '.APP_URL')
DB_CONNECTION=$(echo "$SECRET_JSON" | jq -r '.DB_CONNECTION')

# Validate values
for var in DB_HOST DB_USERNAME DB_DATABASE DB_PASSWORD OPENAI_API_KEY APP_URL DB_CONNECTION; do
    eval value=\$$var
    if [ "$value" = "null" ] || [ "$value" = "" ]; then
        echo "ERROR: $var is null or empty in the secret"
        exit 1
    fi
done

echo "✓ Successfully parsed secrets"

# Update production settings in .env
echo "Updating production settings in .env..."

# Set production environment
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env

# Update values from AWS Secrets Manager
sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=$DB_CONNECTION/" .env
sed -i "s/DB_HOST=127.0.0.1/DB_HOST=$DB_HOST/" .env
sed -i "s/DB_HOST=localhost/DB_HOST=$DB_HOST/" .env
sed -i "s/DB_DATABASE=laravel/DB_DATABASE=$DB_DATABASE/" .env
sed -i "s/DB_DATABASE=learningjourneys/DB_DATABASE=$DB_DATABASE/" .env
sed -i "s/DB_USERNAME=root/DB_USERNAME=$DB_USERNAME/" .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=\"$DB_PASSWORD\"/" .env

# Update OpenAI API key
if grep -q "^OPENAI_API_KEY=" .env; then
    sed -i "s/^OPENAI_API_KEY=.*/OPENAI_API_KEY=$OPENAI_API_KEY/" .env
else
    echo "OPENAI_API_KEY=$OPENAI_API_KEY" >> .env
fi

echo "✓ Updated .env with AWS Secrets Manager values"

echo ""
echo "=== STEP 4: ENSURE DOCUMENTROOT EXISTS ==="

# Create public directory if it doesn't exist
if [ ! -d "$APP_DIR/public" ]; then
    echo "ERROR: Public directory does not exist!"
    echo "This indicates a deployment issue. Available directories:"
    ls -la $APP_DIR/
    exit 1
else
    echo "✓ Public directory exists"
fi

echo ""
echo "=== STEP 5: RESTART APACHE ==="

# Restart Apache to reload configuration
echo "Restarting Apache..."
systemctl restart httpd

if systemctl is-active --quiet httpd; then
    echo "✓ Apache restarted successfully"
else
    echo "ERROR: Apache failed to restart"
    systemctl status httpd --no-pager
    exit 1
fi

echo ""
echo "=== STEP 6: VERIFY FIXES ==="

echo "Checking file ownership:"
ls -la $APP_DIR/ | head -10

echo ""
echo "Checking .env file:"
echo "- Exists: $([ -f $ENV_FILE ] && echo "✓ Yes" || echo "✗ No")"
echo "- Ownership: $(ls -la $ENV_FILE | awk '{print $3":"$4}')"
echo "- Permissions: $(ls -la $ENV_FILE | awk '{print $1}')"

echo ""
echo "Checking Apache status:"
systemctl status httpd --no-pager | head -10

echo ""
echo "=== QUICK FIX COMPLETED SUCCESSFULLY ==="
echo "Your Laravel application should now be working correctly!"
echo ""
echo "Summary of fixes applied:"
echo "1. ✓ Fixed file ownership to ec2-user:apache"
echo "2. ✓ Recreated .env from .env.example template"
echo "3. ✓ Applied AWS Secrets Manager values"
echo "4. ✓ Verified DocumentRoot exists"
echo "5. ✓ Restarted Apache"