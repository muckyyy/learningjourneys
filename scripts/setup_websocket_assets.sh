#!/bin/bash

# Script to update Node.js dependencies and compile assets for WebSocket functionality
# Run this script to install Laravel Echo and Pusher.js dependencies

echo "=== Laravel Echo & Pusher.js Setup ==="
echo ""

echo "1. Installing Node.js dependencies..."
npm install

echo ""
echo "2. Compiling assets with Laravel Mix..."
npm run dev

echo ""
echo "3. Verifying compiled assets..."
if [ -f "public/js/app.js" ]; then
    echo "✅ JavaScript assets compiled successfully"
    JS_SIZE=$(stat -c%s "public/js/app.js" 2>/dev/null || echo "0")
    echo "   app.js size: $JS_SIZE bytes"
else
    echo "❌ JavaScript compilation failed"
    exit 1
fi

if [ -f "public/css/app.css" ]; then
    echo "✅ CSS assets compiled successfully"
    CSS_SIZE=$(stat -c%s "public/css/app.css" 2>/dev/null || echo "0")
    echo "   app.css size: $CSS_SIZE bytes"
else
    echo "❌ CSS compilation failed"
    exit 1
fi

echo ""
echo "4. Checking for Laravel Echo in compiled assets..."
if grep -q "laravel-echo" "public/js/app.js" 2>/dev/null; then
    echo "✅ Laravel Echo found in compiled assets"
else
    echo "⚠️ Laravel Echo not found in compiled assets - check bootstrap.js configuration"
fi

if grep -q "pusher-js" "public/js/app.js" 2>/dev/null; then
    echo "✅ Pusher.js found in compiled assets"
else
    echo "⚠️ Pusher.js not found in compiled assets - check package.json"
fi

echo ""
echo "5. Production compilation (optional)..."
read -p "Would you like to compile for production? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Compiling for production..."
    npm run production
    echo "✅ Production assets compiled"
else
    echo "Skipping production compilation"
fi

echo ""
echo "=== Setup Complete ==="
echo ""
echo "Next steps:"
echo "1. Commit the updated package.json and compiled assets"
echo "2. Deploy to your server"
echo "3. Test WebSocket functionality at /websocket-test-integrated"
echo ""
echo "No more external CDN dependencies needed!"
echo "All WebSocket functionality now uses compiled Laravel assets."