#!/bin/bash

echo "=== CodeDeploy Deployment Diagnostic Script ==="
echo "Timestamp: $(date)"
echo ""

echo "1. Checking CodeDeploy Agent Status:"
if systemctl is-active --quiet codedeploy-agent; then
    echo "✓ CodeDeploy agent is running"
    systemctl status codedeploy-agent --no-pager -l
else
    echo "✗ CodeDeploy agent is not running"
    echo "Attempting to start CodeDeploy agent..."
    sudo systemctl start codedeploy-agent
    sudo systemctl enable codedeploy-agent
fi

echo ""
echo "2. Checking CodeDeploy Agent Logs:"
CODEDEPLOY_LOG="/opt/codedeploy-agent/deployment-root/deployment-logs/codedeploy-agent-deployments.log"
if [ -f "$CODEDEPLOY_LOG" ]; then
    echo "✓ CodeDeploy log file exists"
    echo "Log file permissions: $(ls -l "$CODEDEPLOY_LOG")"
    echo "Log file size: $(wc -l < "$CODEDEPLOY_LOG") lines"
    echo ""
    echo "Last 50 lines from CodeDeploy log:"
    tail -50 "$CODEDEPLOY_LOG"
else
    echo "✗ CodeDeploy log file not found at: $CODEDEPLOY_LOG"
    echo "Searching for CodeDeploy logs in alternative locations..."
    find /opt/codedeploy-agent -name "*.log" -type f 2>/dev/null || echo "No CodeDeploy logs found"
fi

echo ""
echo "3. Checking Deployment Root Directory:"
DEPLOY_ROOT="/opt/codedeploy-agent/deployment-root"
if [ -d "$DEPLOY_ROOT" ]; then
    echo "✓ Deployment root exists: $DEPLOY_ROOT"
    echo "Contents:"
    ls -la "$DEPLOY_ROOT" 2>/dev/null || echo "Cannot list deployment root contents"
    
    # Check for recent deployments
    echo ""
    echo "Recent deployment directories:"
    find "$DEPLOY_ROOT" -maxdepth 2 -name "d-*" -type d 2>/dev/null | head -5 || echo "No deployment directories found"
else
    echo "✗ Deployment root not found: $DEPLOY_ROOT"
fi

echo ""
echo "4. Checking Application Directory:"
APP_DIR="/var/www"
if [ -d "$APP_DIR" ]; then
    echo "✓ Application directory exists: $APP_DIR"
    echo "Contents:"
    ls -la "$APP_DIR" | head -10
    
    echo ""
    echo "Checking for .env file:"
    if [ -f "$APP_DIR/.env" ]; then
        echo "✓ .env file exists"
        echo "File permissions: $(ls -l "$APP_DIR/.env")"
        echo "File size: $(wc -l < "$APP_DIR/.env") lines"
        echo "First few lines:"
        head -5 "$APP_DIR/.env"
    else
        echo "✗ .env file not found"
    fi
    
    echo ""
    echo "Checking Laravel files:"
    [ -f "$APP_DIR/artisan" ] && echo "✓ artisan file exists" || echo "✗ artisan file missing"
    [ -d "$APP_DIR/public" ] && echo "✓ public directory exists" || echo "✗ public directory missing"
    [ -d "$APP_DIR/storage" ] && echo "✓ storage directory exists" || echo "✗ storage directory missing"
else
    echo "✗ Application directory not found: $APP_DIR"
fi

echo ""
echo "5. Checking System Logs for CodeDeploy:"
echo "Recent CodeDeploy entries in system log:"
journalctl -u codedeploy-agent --since "1 hour ago" --no-pager -n 20 2>/dev/null || echo "No recent CodeDeploy entries in systemd journal"

echo ""
echo "6. Checking AWS CLI and Credentials:"
if command -v aws &> /dev/null; then
    echo "✓ AWS CLI is available"
    aws --version
    echo ""
    echo "Testing AWS credentials:"
    if aws sts get-caller-identity 2>/dev/null; then
        echo "✓ AWS credentials are working"
    else
        echo "✗ AWS credentials not working"
    fi
else
    echo "✗ AWS CLI not found"
fi

echo ""
echo "7. Checking CodeDeploy Agent Configuration:"
CODEDEPLOY_CONFIG="/etc/codedeploy-agent/conf/codedeployagent.yml"
if [ -f "$CODEDEPLOY_CONFIG" ]; then
    echo "✓ CodeDeploy config exists"
    echo "Configuration:"
    cat "$CODEDEPLOY_CONFIG" 2>/dev/null || echo "Cannot read config file"
else
    echo "✗ CodeDeploy config not found"
fi

echo ""
echo "8. Manual Script Test:"
echo "Testing if deployment scripts are executable:"
SCRIPT_DIR="/var/www/scripts"
if [ -d "$SCRIPT_DIR" ]; then
    echo "✓ Scripts directory exists"
    ls -la "$SCRIPT_DIR"
    
    echo ""
    echo "Testing configure_environment.sh manually:"
    if [ -f "$SCRIPT_DIR/configure_environment.sh" ]; then
        echo "Script exists, testing execution..."
        chmod +x "$SCRIPT_DIR/configure_environment.sh"
        bash -x "$SCRIPT_DIR/configure_environment.sh" || echo "Script failed"
    else
        echo "configure_environment.sh not found"
    fi
else
    echo "✗ Scripts directory not found: $SCRIPT_DIR"
fi

echo ""
echo "=== Diagnostic completed ==="