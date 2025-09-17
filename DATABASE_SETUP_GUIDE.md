# External Database Configuration Guide

## Overview
The application has been modified to connect to an external MariaDB/MySQL database instead of running MariaDB on the same EC2 instance. This provides better separation of concerns and scalability.

## Required Infrastructure Changes

### 1. Database EC2 Instance
Create a separate EC2 instance for the database with:
- **AMI**: Amazon Linux 2023
- **Instance Type**: t3.medium or larger (depending on your workload)
- **Security Group**: Allow inbound MySQL/MariaDB traffic (port 3306) from the application EC2 instances

### 2. Database Installation on DB EC2 Instance
```bash
# Install MariaDB
sudo dnf update -y
sudo dnf install -y mariadb105-server mariadb105

# Start and enable MariaDB
sudo systemctl start mariadb
sudo systemctl enable mariadb

# Secure MariaDB installation
sudo mysql_secure_installation

# Create the application database
sudo mysql -u root -p
```

```sql
CREATE DATABASE learningjourneys CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'appuser'@'%' IDENTIFIED BY 'your-secure-password';
GRANT ALL PRIVILEGES ON learningjourneys.* TO 'appuser'@'%';
FLUSH PRIVILEGES;
EXIT;
```

### 3. MariaDB Configuration
Edit `/etc/mysql/mariadb.conf.d/50-server.cnf` (or `/etc/my.cnf.d/mariadb-server.cnf` on Amazon Linux):
```ini
[mysqld]
bind-address = 0.0.0.0
port = 3306
```

Restart MariaDB:
```bash
sudo systemctl restart mariadb
```

### 4. Security Group Configuration

#### Database EC2 Security Group
- **Inbound Rules**:
  - Type: MySQL/Aurora (3306)
  - Source: Application EC2 security group or VPC CIDR
  - SSH (22) for administration

#### Application EC2 Security Group  
- **Outbound Rules**:
  - Type: MySQL/Aurora (3306)
  - Destination: Database EC2 security group or IP

### 5. AWS Secrets Manager Update

Update your `learningjourneys/keys` secret in AWS Secrets Manager to include:

```json
{
    "DB_HOST": "your-database-ec2-private-ip-or-dns",
    "DB_USER": "appuser",
    "DB_DATABASE": "learningjourneys",
    "DB_PASSWORD": "your-secure-database-password", 
    "OPENAI_API_KEY": "your-openai-api-key"
}
```

**Important**: Use the **private IP address** or private DNS name of the database EC2 instance for security.

### 6. Network Considerations

#### Option A: Same VPC (Recommended)
- Place both EC2 instances in the same VPC
- Use private IP addresses for database connection
- Configure security groups to allow communication

#### Option B: Different VPCs
- Set up VPC peering or Transit Gateway
- Ensure routing tables allow communication
- Update security groups accordingly

## Database Schema Migration

The application will automatically run migrations on deployment. However, for the first deployment:

1. **Ensure the database exists** on the external server
2. **Verify network connectivity** from app server to DB server
3. **Test credentials** before deployment

## Troubleshooting

### Connection Issues
```bash
# Test connectivity from application server
telnet your-db-server-ip 3306

# Test MySQL connection
mysql -h your-db-server-ip -u root -p

# Check Laravel database connection
cd /var/www
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Connected!';"
```

### Common Issues
1. **Security Group blocking port 3306**
2. **MariaDB bind-address not set to 0.0.0.0**
3. **Database user doesn't have remote access permissions**
4. **Wrong IP address in AWS Secrets Manager**
5. **Database server firewall blocking connections**

## Monitoring

Monitor the database server separately from the application servers:
- Database performance metrics
- Connection counts  
- Query performance
- Disk space usage
- Memory utilization

## Backup Strategy

Set up automated backups for the database server:
```bash
# Create backup script
mysqldump -u root -p learningjourneys > backup_$(date +%Y%m%d_%H%M%S).sql

# Schedule with cron
0 2 * * * /path/to/backup-script.sh
```

Consider using AWS RDS instead of self-managed MariaDB for automated backups, monitoring, and high availability.