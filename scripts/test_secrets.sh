#!/bin/bash
# Test script to verify AWS Secrets Manager integration
# This script can be run manually on the EC2 instance to debug secrets issues

set -e

echo "=== AWS Secrets Manager Debug Script ==="
echo "Testing access to 'learningjourneys/keys' secret in eu-west-1"
echo ""

# Check AWS CLI installation
echo "1. Checking AWS CLI..."
if ! command -v aws &> /dev/null; then
    echo "❌ AWS CLI is not installed"
    echo "Install with: dnf install -y awscli"
    exit 1
fi
echo "✅ AWS CLI is installed: $(aws --version)"
echo ""

# Check AWS credentials/identity
echo "2. Checking AWS credentials..."
if ! AWS_IDENTITY=$(aws sts get-caller-identity 2>&1); then
    echo "❌ AWS credentials not configured or not working"
    echo "Error details: $AWS_IDENTITY"
    echo ""
    echo "Troubleshooting steps:"
    echo "1. Ensure EC2 instance has an IAM role attached"
    echo "2. Verify IAM role has 'secretsmanager:GetSecretValue' permission"
    echo "3. Check if AWS CLI is configured with 'aws configure list'"
    exit 1
fi
echo "✅ AWS credentials working"
echo "Current identity: $AWS_IDENTITY"
echo ""

# Check if secret exists and is accessible
echo "3. Checking secret accessibility..."
if ! aws secretsmanager describe-secret --secret-id "learningjourneys/keys" --region "eu-west-1" >/dev/null 2>&1; then
    echo "❌ Cannot access secret 'learningjourneys/keys' in eu-west-1"
    echo ""
    echo "Troubleshooting steps:"
    echo "1. Verify secret exists in AWS Secrets Manager console"
    echo "2. Check if you're using the correct region (eu-west-1)"
    echo "3. Verify IAM role has 'secretsmanager:DescribeSecret' permission"
    echo "4. Confirm the exact secret name/ARN"
    echo ""
    echo "Available secrets in eu-west-1:"
    aws secretsmanager list-secrets --region "eu-west-1" --query "SecretList[].Name" --output table 2>/dev/null || echo "Cannot list secrets (permission denied)"
    exit 1
fi
echo "✅ Secret 'learningjourneys/keys' is accessible"

# Get secret metadata
SECRET_INFO=$(aws secretsmanager describe-secret --secret-id "learningjourneys/keys" --region "eu-west-1" 2>/dev/null)
echo "Secret ARN: $(echo "$SECRET_INFO" | jq -r '.ARN')"
echo "Secret description: $(echo "$SECRET_INFO" | jq -r '.Description // "No description"')"
echo "Last modified: $(echo "$SECRET_INFO" | jq -r '.LastChangedDate')"
echo ""

# Check jq availability
echo "4. Checking JSON parsing tool (jq)..."
if ! command -v jq &> /dev/null; then
    echo "❌ jq is not installed - installing it now..."
    if ! dnf install -y jq; then
        echo "❌ Failed to install jq"
        exit 1
    fi
    echo "✅ jq installed successfully"
else
    echo "✅ jq is available: $(jq --version)"
fi
echo ""

# Fetch the actual secret value
echo "5. Fetching secret value..."
if ! SECRET_JSON=$(aws secretsmanager get-secret-value \
    --secret-id "learningjourneys/keys" \
    --region "eu-west-1" \
    --query SecretString \
    --output text 2>&1); then
    echo "❌ Failed to fetch secret value"
    echo "Error details: $SECRET_JSON"
    exit 1
fi
echo "✅ Successfully fetched secret value"
echo "Secret JSON length: ${#SECRET_JSON} characters"
echo ""

# Validate JSON format
echo "6. Validating JSON format..."
if ! echo "$SECRET_JSON" | jq . >/dev/null 2>&1; then
    echo "❌ Invalid JSON received from AWS Secrets Manager"
    echo "Raw secret data (first 200 chars): ${SECRET_JSON:0:200}"
    exit 1
fi
echo "✅ Secret contains valid JSON"

# Show available keys (without values)
echo "Available keys in secret: $(echo "$SECRET_JSON" | jq -r 'keys[]' | tr '\n' ' ')"
echo ""

# Extract and validate specific keys
echo "7. Extracting required keys..."

if ! DB_HOST=$(echo "$SECRET_JSON" | jq -r '.DB_HOST' 2>&1); then
    echo "❌ Failed to extract DB_HOST"
    echo "Error: $DB_HOST"
    exit 1
fi

if ! DB_USERNAME=$(echo "$SECRET_JSON" | jq -r '.DB_USERNAME' 2>&1); then
    echo "❌ Failed to extract DB_USERNAME"
    echo "Error: $DB_USERNAME"
    exit 1
fi

if ! DB_DATABASE=$(echo "$SECRET_JSON" | jq -r '.DB_DATABASE' 2>&1); then
    echo "❌ Failed to extract DB_DATABASE"
    echo "Error: $DB_DATABASE"
    exit 1
fi

if ! DB_PASSWORD=$(echo "$SECRET_JSON" | jq -r '.DB_PASSWORD' 2>&1); then
    echo "❌ Failed to extract DB_PASSWORD"
    echo "Error: $DB_PASSWORD"
    exit 1
fi

if ! OPENAI_API_KEY=$(echo "$SECRET_JSON" | jq -r '.OPENAI_API_KEY' 2>&1); then
    echo "❌ Failed to extract OPENAI_API_KEY"
    echo "Error: $OPENAI_API_KEY"
    exit 1
fi

# Validate values are not null or empty
if [ "$DB_HOST" = "null" ] || [ -z "$DB_HOST" ]; then
    echo "❌ DB_HOST is null or empty"
    exit 1
fi

if [ "$DB_USERNAME" = "null" ] || [ -z "$DB_USERNAME" ]; then
    echo "❌ DB_USERNAME is null or empty"
    exit 1
fi

if [ "$DB_DATABASE" = "null" ] || [ -z "$DB_DATABASE" ]; then
    echo "❌ DB_DATABASE is null or empty"
    exit 1
fi

if [ "$DB_PASSWORD" = "null" ] || [ -z "$DB_PASSWORD" ]; then
    echo "❌ DB_PASSWORD is null or empty"
    exit 1
fi

if [ "$OPENAI_API_KEY" = "null" ] || [ -z "$OPENAI_API_KEY" ]; then
    echo "❌ OPENAI_API_KEY is null or empty"
    exit 1
fi

echo "✅ Successfully extracted all required keys"
echo "DB_HOST: $DB_HOST"
echo "DB_USERNAME: $DB_USERNAME"
echo "DB_DATABASE: $DB_DATABASE"
echo "DB_PASSWORD length: ${#DB_PASSWORD} characters"
echo "OPENAI_API_KEY length: ${#OPENAI_API_KEY} characters"
echo ""

echo "=== SUCCESS ==="
echo "✅ AWS Secrets Manager integration is working correctly"
echo "✅ All required secrets are available and properly formatted"
echo ""
echo "If this test passes but deployment still fails, the issue might be:"
echo "1. CodeDeploy running as a different user/role"
echo "2. Different AWS region configured during deployment"
echo "3. Network connectivity issues during deployment"
echo "4. File permissions preventing .env updates"