# AWS Secrets Manager Troubleshooting Guide

## Problem: Secrets not being loaded (DB_PASSWORD and OPENAI_API_KEY empty)

The enhanced deployment script now includes detailed error checking. Here's how to troubleshoot:

### 1. Run the Debug Script

First, SSH into your EC2 instance and run the debug script:

```bash
# Make the script executable
chmod +x /var/www/learningjourneys/scripts/test_secrets.sh

# Run the debug script
/var/www/learningjourneys/scripts/test_secrets.sh
```

### 2. Common Issues and Solutions

#### Issue A: IAM Permissions
**Error**: "AWS credentials not configured" or "Cannot access secret"

**Solution**: Ensure your EC2 instance has an IAM role with these permissions:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "secretsmanager:GetSecretValue",
                "secretsmanager:DescribeSecret"
            ],
            "Resource": "arn:aws:secretsmanager:eu-west-1:*:secret:learningjourneys/keys*"
        }
    ]
}
```

To attach IAM role to EC2:
1. Go to EC2 Console → Instances
2. Select your instance → Actions → Security → Modify IAM role
3. Attach role with above permissions

#### Issue B: Secret Doesn't Exist
**Error**: "Cannot access secret 'learningjourneys/keys' in eu-west-1"

**Solution**: Create the secret in AWS Secrets Manager:
1. Go to AWS Secrets Manager console
2. Choose "Store a new secret"
3. Select "Other type of secret"
4. Add key-value pairs:
   - Key: `DB_PASSWORD`, Value: Your MySQL root password
   - Key: `OPENAI_API_KEY`, Value: Your OpenAI API key
5. Name: `learningjourneys/keys`
6. Region: `eu-west-1`

#### Issue C: Wrong Region
**Error**: Secret not found or permission denied

**Solution**: Ensure the secret exists in `eu-west-1` region
- Check current region: `aws configure get region`
- Set region if needed: `aws configure set region eu-west-1`

#### Issue D: JSON Format Issues
**Error**: "Invalid JSON received" or "Failed to extract keys"

**Solution**: Verify secret format in AWS console:
```json
{
    "DB_PASSWORD": "your_mysql_password",
    "OPENAI_API_KEY": "sk-your-openai-key"
}
```

### 3. Manual Testing Commands

Test each step manually on the EC2 instance:

```bash
# 1. Check AWS CLI and credentials
aws sts get-caller-identity

# 2. List available secrets
aws secretsmanager list-secrets --region eu-west-1

# 3. Check if specific secret exists
aws secretsmanager describe-secret --secret-id "learningjourneys/keys" --region eu-west-1

# 4. Fetch secret value
aws secretsmanager get-secret-value --secret-id "learningjourneys/keys" --region eu-west-1 --query SecretString --output text

# 5. Parse with jq
SECRET_JSON=$(aws secretsmanager get-secret-value --secret-id "learningjourneys/keys" --region eu-west-1 --query SecretString --output text)
echo $SECRET_JSON | jq '.DB_PASSWORD'
echo $SECRET_JSON | jq '.OPENAI_API_KEY'
```

### 4. CodeDeploy-Specific Issues

If manual testing works but CodeDeploy fails:

#### Check CodeDeploy Service Role
The CodeDeploy service role also needs permissions:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "secretsmanager:GetSecretValue"
            ],
            "Resource": "arn:aws:secretsmanager:eu-west-1:*:secret:learningjourneys/keys*"
        }
    ]
}
```

#### Check CodeDeploy Agent Logs
```bash
# Check CodeDeploy agent logs
sudo tail -f /var/log/aws/codedeploy-agent/codedeploy-agent.log

# Check deployment-specific logs
sudo ls -la /opt/codedeploy-agent/deployment-root/
# Look for your deployment ID and check the logs
```

#### Verify Script Execution Context
CodeDeploy runs scripts as the `root` user, but environment might be different:

```bash
# Add to beginning of after_install.sh for debugging:
echo "Current user: $(whoami)"
echo "AWS credentials file: $(ls -la ~/.aws/ 2>/dev/null || echo 'No AWS credentials file')"
echo "Environment variables:"
env | grep AWS
```

### 5. Fallback Solution: Environment Variables

If secrets manager continues to fail, you can set secrets as CodeDeploy environment variables:

```yaml
# In buildspec.yml
version: 0.2
phases:
  build:
    commands:
      - echo "Build completed"
artifacts:
  files:
    - '**/*'
  base-directory: .
environment:
  variables:
    DB_PASSWORD: "your_password_here"  # Not recommended for production
    OPENAI_API_KEY: "your_key_here"   # Not recommended for production
```

### 6. Enhanced Deployment Monitoring

The updated `after_install.sh` script now provides detailed output. Check CodeDeploy logs for:
- "✓ AWS CLI working - Identity: ..."
- "✓ Secret 'learningjourneys/keys' is accessible"
- "✓ Successfully fetched secrets from AWS Secrets Manager"
- "✓ Successfully parsed secrets - DB_PASSWORD length: X, OPENAI_API_KEY length: Y"
- "✓ Successfully updated and verified .env file with secrets"

If any of these steps fail, you'll see specific error messages indicating the exact problem.

### 7. Security Considerations

- Never log actual secret values
- Use IAM roles instead of access keys when possible
- Restrict secret access to minimum required resources
- Regularly rotate secrets
- Monitor secret access via CloudTrail

### 8. Alternative: AWS Systems Manager Parameter Store

If Secrets Manager continues to have issues, consider using Parameter Store:

```bash
# Store secrets in Parameter Store (SecureString type)
aws ssm put-parameter --name "/learningjourneys/db_password" --value "your_password" --type "SecureString" --region eu-west-1
aws ssm put-parameter --name "/learningjourneys/openai_key" --value "your_key" --type "SecureString" --region eu-west-1

# Retrieve in script
DB_PASSWORD=$(aws ssm get-parameter --name "/learningjourneys/db_password" --with-decryption --region eu-west-1 --query "Parameter.Value" --output text)
OPENAI_API_KEY=$(aws ssm get-parameter --name "/learningjourneys/openai_key" --with-decryption --region eu-west-1 --query "Parameter.Value" --output text)
```