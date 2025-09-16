#!/bin/bash
set -e

APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

echo "=== Configuring environment ==="

# Check if we're in the right directory
if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: Application directory $APP_DIR does not exist"
    exit 1
fi

cd $APP_DIR
echo "Working in directory: $(pwd)"

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
    echo "ERROR: DB_PASSWORD is null or empty"
    exit 1
fi

if [ "$OPENAI_API_KEY" = "null" ] || [ -z "$OPENAI_API_KEY" ]; then
    echo "ERROR: OPENAI_API_KEY is null or empty"
    exit 1
fi

echo "✓ Successfully extracted secrets"

# Generate APP_KEY if it doesn't exist
echo "Checking for existing .env file..."
if [ ! -f "$ENV_FILE" ]; then
    echo "Creating new .env file..."
    touch $ENV_FILE
else
    echo "✓ .env file already exists"
fi

# Check if APP_KEY exists and is not empty
echo "Checking APP_KEY..."
APP_KEY=$(grep "^APP_KEY=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2 | tr -d '"' || echo "")
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "Generating new APP_KEY..."
    if ! php artisan key:generate --force; then
        echo "ERROR: Failed to generate APP_KEY"
        exit 1
    fi
    echo "✓ APP_KEY generated successfully"
    # Re-read the generated key
    APP_KEY=$(grep "^APP_KEY=" "$ENV_FILE" | cut -d '=' -f2)
else
    echo "✓ APP_KEY already exists"
fi

# Create/update .env file with production values
echo "Creating production .env file..."
cat > "$ENV_FILE" << EOF
APP_NAME="Learning Journeys"
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=https://the-thinking-course.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=learningjourneys
DB_USERNAME=root
DB_PASSWORD="$DB_PASSWORD"

BROADCAST_DRIVER=pusher
CACHE_DRIVER=file
FILESYSTEM_DRIVER=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="\${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

MIX_PUSHER_APP_KEY="\${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="\${PUSHER_APP_CLUSTER}"

OPENAI_API_KEY="$OPENAI_API_KEY"

# WebSocket Configuration
LARAVEL_WEBSOCKETS_PORT=6001
LARAVEL_WEBSOCKETS_HOST=0.0.0.0
LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT=
LARAVEL_WEBSOCKETS_SSL_LOCAL_PK=
LARAVEL_WEBSOCKETS_SSL_PASSPHRASE=
EOF

echo "✓ .env file created successfully"

# Set proper ownership and permissions for .env file
echo "Setting .env file permissions..."
chown apache:apache "$ENV_FILE"
chmod 600 "$ENV_FILE"
echo "✓ .env file permissions set"

echo "=== Environment configuration completed successfully ==="
