#!/bin/bash

echo "=== Environment Configuration Debug Script ==="

APP_DIR="/var/www"
ENV_FILE="$APP_DIR/.env"

echo "1. Checking application directory:"
if [ -d "$APP_DIR" ]; then
    echo "✓ Application directory exists: $APP_DIR"
    ls -la "$APP_DIR" | head -5
else
    echo "✗ Application directory does not exist: $APP_DIR"
fi

echo ""
echo "2. Checking .env file:"
if [ -f "$ENV_FILE" ]; then
    echo "✓ .env file exists: $ENV_FILE"
    echo "File permissions: $(ls -l "$ENV_FILE")"
    echo "File size: $(wc -l < "$ENV_FILE") lines"
    echo ""
    echo "First 10 lines of .env file:"
    head -10 "$ENV_FILE"
else
    echo "✗ .env file does not exist: $ENV_FILE"
fi

echo ""
echo "3. Checking AWS CLI:"
if command -v aws &> /dev/null; then
    echo "✓ AWS CLI is available"
    aws --version
    echo ""
    echo "Testing AWS credentials:"
    if aws sts get-caller-identity 2>/dev/null; then
        echo "✓ AWS credentials are working"
    else
        echo "✗ AWS credentials not working or not configured"
    fi
else
    echo "✗ AWS CLI not found"
fi

echo ""
echo "4. Checking jq:"
if command -v jq &> /dev/null; then
    echo "✓ jq is available"
    jq --version
else
    echo "✗ jq not found"
fi

echo ""
echo "5. Testing secrets access:"
if aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text &> /dev/null; then
    echo "✓ Can access AWS Secrets Manager"
    echo "Secret structure:"
    aws secretsmanager get-secret-value \
        --secret-id "learningjourneys/keys" \
        --region "eu-west-1" \
        --query SecretString \
        --output text | jq 'keys'
else
    echo "✗ Cannot access AWS Secrets Manager"
fi

echo ""
echo "6. Checking CodeDeploy agent logs (last 20 lines):"
if [ -f "/opt/codedeploy-agent/deployment-root/deployment-logs/codedeploy-agent-deployments.log" ]; then
    echo "Recent CodeDeploy logs:"
    tail -20 /opt/codedeploy-agent/deployment-root/deployment-logs/codedeploy-agent-deployments.log | grep -E "(configure_environment|ERROR|FAILED)" || echo "No relevant log entries found"
else
    echo "CodeDeploy logs not found"
fi

echo ""
echo "=== Debug completed ==="