#!/bin/bash
set -e

APP_DIR="/var/www/learningjourneys"
ENV_FILE="$APP_DIR/.env"

echo "Configuring environment..."

cd $APP_DIR

# Fetch secrets from AWS Secrets Manager
echo "Fetching secrets from AWS Secrets Manager..."
SECRET_JSON=$(aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text)

# Extract individual secrets
DB_PASSWORD=$(echo $SECRET_JSON | jq -r '.DB_PASSWORD')
OPENAI_API_KEY=$(echo $SECRET_JSON | jq -r '.OPENAI_API_KEY')

# Generate APP_KEY if it doesn't exist
if [ ! -f "$ENV_FILE" ]; then
    echo "Creating .env file..."
    touch $ENV_FILE
fi

# Check if APP_KEY exists and is not empty
APP_KEY=$(grep "^APP_KEY=" $ENV_FILE | cut -d '=' -f2 | tr -d '"' || echo "")
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "Generating new APP_KEY..."
    php artisan key:generate --force
fi

# Create/update .env file with production values
cat > $ENV_FILE << EOF
APP_NAME="Learning Journeys"
APP_ENV=production
APP_KEY=$(grep "^APP_KEY=" $ENV_FILE | cut -d '=' -f2)
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

# Set proper ownership and permissions for .env file
chown apache:apache $ENV_FILE
chmod 600 $ENV_FILE

echo "Environment configuration completed"
