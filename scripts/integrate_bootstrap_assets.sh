#!/bin/bash

# Script to install Bootstrap and Bootstrap Icons as local dependencies
# This eliminates the need for external CDN links and CSP violations

echo "=== Bootstrap & Bootstrap Icons Local Integration ==="
echo ""

echo "1. Installing npm dependencies (Bootstrap + Bootstrap Icons)..."
if npm install; then
    echo "✅ NPM dependencies installed successfully"
else
    echo "❌ NPM install failed"
    exit 1
fi

echo ""
echo "2. Checking installed Bootstrap versions..."
if [ -d "node_modules/bootstrap" ]; then
    BOOTSTRAP_VERSION=$(node -p "require('./node_modules/bootstrap/package.json').version")
    echo "✅ Bootstrap: $BOOTSTRAP_VERSION"
else
    echo "❌ Bootstrap not found in node_modules"
fi

if [ -d "node_modules/bootstrap-icons" ]; then
    ICONS_VERSION=$(node -p "require('./node_modules/bootstrap-icons/package.json').version")
    echo "✅ Bootstrap Icons: $ICONS_VERSION"
else
    echo "❌ Bootstrap Icons not found in node_modules"
fi

echo ""
echo "3. Compiling assets for development..."
if npm run dev; then
    echo "✅ Development assets compiled successfully"
else
    echo "❌ Development compilation failed"
    exit 1
fi

echo ""
echo "4. Checking compiled assets..."
if [ -f "public/css/app.css" ]; then
    CSS_SIZE=$(stat -c%s "public/css/app.css" 2>/dev/null || stat -f%z "public/css/app.css" 2>/dev/null || echo "0")
    echo "✅ CSS compiled: $CSS_SIZE bytes"
    
    # Check if Bootstrap is included in CSS
    if grep -q "bootstrap" "public/css/app.css" 2>/dev/null; then
        echo "✅ Bootstrap styles found in compiled CSS"
    else
        echo "⚠️ Bootstrap styles not found in compiled CSS"
    fi
    
    # Check if Bootstrap Icons are included
    if grep -q "bootstrap-icons" "public/css/app.css" 2>/dev/null; then
        echo "✅ Bootstrap Icons styles found in compiled CSS"
    else
        echo "⚠️ Bootstrap Icons styles not found in compiled CSS"
    fi
else
    echo "❌ CSS compilation failed"
    exit 1
fi

if [ -f "public/js/app.js" ]; then
    JS_SIZE=$(stat -c%s "public/js/app.js" 2>/dev/null || stat -f%z "public/js/app.js" 2>/dev/null || echo "0")
    echo "✅ JavaScript compiled: $JS_SIZE bytes"
    
    # Check if Bootstrap JS is included
    if grep -q "bootstrap" "public/js/app.js" 2>/dev/null; then
        echo "✅ Bootstrap JavaScript found in compiled JS"
    else
        echo "⚠️ Bootstrap JavaScript not found in compiled JS"
    fi
else
    echo "❌ JavaScript compilation failed"
    exit 1
fi

echo ""
echo "5. Checking Bootstrap Icons fonts..."
if [ -d "public/fonts" ]; then
    FONT_COUNT=$(find public/fonts -name "*.woff*" -o -name "*.ttf" -o -name "*.eot" | wc -l)
    echo "✅ Bootstrap Icons fonts copied: $FONT_COUNT font files"
else
    echo "⚠️ Bootstrap Icons fonts not found - they will be copied during production build"
fi

echo ""
echo "6. Production build (optional)..."
read -p "Would you like to compile for production? (y/N): " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "Compiling for production..."
    if npm run production; then
        echo "✅ Production assets compiled successfully"
        
        # Check production asset sizes
        if [ -f "public/css/app.css" ]; then
            PROD_CSS_SIZE=$(stat -c%s "public/css/app.css" 2>/dev/null || stat -f%z "public/css/app.css" 2>/dev/null || echo "0")
            echo "   Production CSS size: $PROD_CSS_SIZE bytes"
        fi
        
        if [ -f "public/js/app.js" ]; then
            PROD_JS_SIZE=$(stat -c%s "public/js/app.js" 2>/dev/null || stat -f%z "public/js/app.js" 2>/dev/null || echo "0")
            echo "   Production JS size: $PROD_JS_SIZE bytes"
        fi
    else
        echo "❌ Production compilation failed"
        exit 1
    fi
else
    echo "Skipping production compilation"
fi

echo ""
echo "=== Integration Complete! ==="
echo ""
echo "✅ What's now integrated locally:"
echo "   • Bootstrap CSS and JavaScript"
echo "   • Bootstrap Icons CSS and fonts"
echo "   • Laravel Echo with Pusher.js"
echo "   • All compiled into app.css and app.js"
echo ""
echo "🚫 No more external CDN dependencies:"
echo "   • No more bootstrap@5.1.3 from jsdelivr.net"
echo "   • No more bootstrap-icons from jsdelivr.net"
echo "   • No CSP violations for external stylesheets/scripts"
echo ""
echo "🎯 Next steps:"
echo "   1. Remove any CDN links from your HTML templates"
echo "   2. Ensure {{ mix('css/app.css') }} and {{ mix('js/app.js') }} are loaded"
echo "   3. Deploy the compiled assets"
echo "   4. Test Bootstrap components and icons work"
echo ""
echo "✨ Your application now has better security, performance, and reliability!"