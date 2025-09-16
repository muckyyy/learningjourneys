#!/bin/bash
set -e

echo "Stopping services..."

# Stop Laravel WebSocket service if running
if systemctl is-active --quiet laravel-websockets 2>/dev/null; then
    echo "Stopping Laravel WebSockets service..."
    systemctl stop laravel-websockets
fi

# Stop Apache if running (optional - for zero downtime we might skip this)
if systemctl is-active --quiet httpd; then
    echo "Stopping Apache..."
    systemctl stop httpd
fi

echo "Services stopped"