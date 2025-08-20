#!/bin/bash
###############################################################################
# Production Build Script
# samfedbiz.com - Federal BD Platform
# 
# Creates optimized production build excluding development-only files
###############################################################################

set -e

PROJECT_ROOT="/mnt/d/samfedbiz"
PUBLIC_DIR="$PROJECT_ROOT/public"
JS_DIR="$PUBLIC_DIR/js"
BUILD_DIR="$PROJECT_ROOT/build"

echo "🏗️  Building production version..."

# Create build directory
mkdir -p "$BUILD_DIR/js"

# Core JavaScript files for production (excluding large dev files)
CORE_JS_FILES=(
    "animations.js"
    "smooth-scroll.js"
    "contrast-check.js"
    "accessibility.js"
    "accessibility-core.js"
    "sfbai-chat.js"
    "program-overview.js"
    "outreach-composer.js"
    "analytics-charts.js"
)

# Copy core JS files
total_size=0
for file in "${CORE_JS_FILES[@]}"; do
    if [ -f "$JS_DIR/$file" ]; then
        cp "$JS_DIR/$file" "$BUILD_DIR/js/"
        file_size=$(stat -c%s "$JS_DIR/$file" 2>/dev/null || stat -f%z "$JS_DIR/$file" 2>/dev/null || echo 0)
        total_size=$((total_size + file_size))
        echo "✅ Included: $file ($(( file_size / 1024 ))KB)"
    fi
done

total_size_kb=$((total_size / 1024))
echo ""
echo "📦 Production JS bundle: ${total_size_kb}KB"

# Check if under budget
if [ $total_size_kb -le 250 ]; then
    echo "✅ Under 250KB performance budget!"
else
    echo "❌ Exceeds 250KB performance budget"
    exit 1
fi

echo ""
echo "🎉 Production build completed successfully!"