# AWS CodeDeploy Setup for Learning Journeys Laravel Application

This repository contains all the necessary configuration files and scripts for deploying the Learning Journeys Laravel application to Amazon Linux 2023 using AWS CodeBuild and CodeDeploy.

## Prerequisites

### AWS Services Required
- **EC2 Instance**: Amazon Linux 2023 with appropriate IAM roles
- **CodeBuild Project**: For building and packaging the application
- **CodeDeploy Application**: For deploying to EC2
- **AWS Secrets Manager**: For storing sensitive configuration values
- **S3 Bucket**: For storing deployment artifacts

### EC2 Instance Requirements
- **OS**: Amazon Linux 2023 (AL2023)
- **Instance Type**: t3.medium or higher (recommended for MariaDB + Apache + WebSockets)
- **Security Groups**: 
  - Port 80 (HTTP)
  - Port 443 (HTTPS)
  - Port 6001 (WebSockets)
  - Port 22 (SSH for management)
- **Package Manager**: Uses `dnf` instead of `yum`

### IAM Roles Required

#### EC2 Instance Role
The EC2 instance needs the following permissions:
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
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-codedeploy-bucket/*",
                "arn:aws:s3:::your-codedeploy-bucket"
            ]
        }
    ]
}
```

#### CodeBuild Service Role
Standard CodeBuild permissions plus:
```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-codedeploy-bucket/*",
                "arn:aws:s3:::your-codedeploy-bucket"
            ]
        }
    ]
}
```

## Setup Instructions

### 1. Prepare AWS Secrets Manager

Create a secret named `learningjourneys/keys` in the `eu-west-1` region with the following key-value pairs:
```json
{
    "DB_PASSWORD": "your-secure-database-password",
    "OPENAI_API_KEY": "your-openai-api-key"
}
```

### 2. EC2 Instance Preparation

Install the CodeDeploy agent on your EC2 instance:
```bash
sudo dnf update -y
sudo dnf install -y ruby wget
cd /home/ec2-user
wget https://aws-codedeploy-eu-west-1.s3.eu-west-1.amazonaws.com/latest/install
chmod +x ./install
sudo ./install auto
sudo service codedeploy-agent start
sudo systemctl enable codedeploy-agent
```

### 3. CodeBuild Project Setup

Create a new CodeBuild project with:
- **Source**: Your GitHub repository
- **Environment**: 
  - Operating System: Amazon Linux
  - Runtime: Standard
  - Image: aws/codebuild/amazonlinux2-x86_64-standard:5.0 (or latest)
- **Buildspec**: Use buildspec file in source code
- **Artifacts**: 
  - Type: S3
  - Bucket: Your deployment artifacts bucket
  - Artifacts packaging: Zip

### 4. CodeDeploy Application Setup

Create a CodeDeploy application:
- **Application name**: `learningjourneys-app`
- **Compute platform**: EC2/On-premises
- **Deployment group**: Configure with your EC2 instances
- **Deployment configuration**: CodeDeployDefault.EC2OneAtTime

### 5. File Structure Overview

```
├── appspec.yml                          # CodeDeploy application specification
├── buildspec.yml                        # CodeBuild build specification
├── config/
│   ├── apache/
│   │   └── learningjourneys.conf        # Apache virtual host configuration
│   └── systemd/
│       └── laravel-websockets.service   # WebSocket service configuration
└── scripts/
    ├── install_dependencies.sh          # Install system dependencies
    ├── stop_server.sh                   # Stop services before deployment
    ├── configure_environment.sh         # Configure environment variables
    ├── setup_database.sh                # Database setup and migrations
    ├── set_permissions.sh               # Set file permissions
    ├── start_server.sh                  # Start services after deployment
    └── validate_service.sh              # Validate successful deployment
```

## Deployment Process

### Automatic Deployment Flow

1. **CodeBuild Phase**:
   - Install PHP 8.2 and Node.js 18
   - Install Composer dependencies (`composer install --no-dev`)
   - Build frontend assets (`npm run production`)
   - Create deployment artifact

2. **CodeDeploy Phase**:
   - **BeforeInstall**: Install system dependencies, stop services
   - **AfterInstall**: Configure environment, setup database, set permissions
   - **ApplicationStart**: Start Apache and WebSocket services
   - **ValidateService**: Verify deployment success

### Manual Deployment (for testing)

1. Build the application locally:
```bash
composer install --no-dev --optimize-autoloader
npm run production
```

2. Create deployment package:
```bash
zip -r deployment.zip . -x "node_modules/*" "tests/*" ".git/*" "*.md"
```

3. Deploy using AWS CLI:
```bash
aws deploy create-deployment \
    --application-name learningjourneys-app \
    --deployment-group-name your-deployment-group \
    --s3-location bucket=your-bucket,key=deployment.zip,bundleType=zip
```

## Configuration Details

### Environment Variables
- **Production URL**: https://the-thinking-course.com
- **Database**: MariaDB on localhost:3306
- **WebSocket Port**: 6001
- **SSL**: Configured for HTTPS redirect

### Services
- **Apache**: Web server with virtual host configuration
- **MariaDB**: Database server
- **Laravel WebSockets**: Real-time communication service

### Security Features
- Secure file permissions (644/755 for files/directories, 775 for writable dirs)
- Environment file protection (600 permissions)
- Security headers in Apache configuration
- HTTPS enforcement
- Sensitive directory access denial

## Monitoring and Troubleshooting

### Service Status Checks
```bash
# Check service status
sudo systemctl status httpd
sudo systemctl status mariadb
sudo systemctl status laravel-websockets

# Check logs
sudo tail -f /var/log/httpd/learningjourneys_error.log
sudo journalctl -u laravel-websockets -f
```

### Common Issues

1. **Database Connection Fails**: Check MariaDB status and credentials in AWS Secrets Manager
2. **WebSocket Connection Issues**: Verify port 6001 is open in security groups
3. **Permission Errors**: Run the permissions script manually: `/var/www/learningjourneys/scripts/set_permissions.sh`
4. **SSL Issues**: Verify certificate paths in Apache configuration

### Application Logs
Laravel logs are available at: `/var/www/learningjourneys/storage/logs/laravel.log`

## Post-Deployment Verification

After deployment, verify:
1. Website is accessible at https://the-thinking-course.com
2. Database migrations have run successfully
3. WebSocket service is running on port 6001
4. All services are enabled for auto-start

## Support

For deployment issues, check:
1. CodeBuild logs in AWS Console
2. CodeDeploy deployment events
3. EC2 instance system logs
4. Application logs in `/var/www/learningjourneys/storage/logs/`
