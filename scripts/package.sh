#!/bin/bash
#
# Package IntentPress plugin for distribution
#
# Usage: npm run package
#

set -e

# Configuration
PLUGIN_SLUG="intentpress"
VERSION=$(node -p "require('./package.json').version")
BUILD_DIR="dist"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"

echo "📦 Packaging IntentPress v${VERSION}..."

# Clean up previous builds
rm -rf "${BUILD_DIR}"
mkdir -p "${PACKAGE_DIR}"

# Copy required files
echo "📁 Copying files..."

# Main plugin file
cp intentpress.php "${PACKAGE_DIR}/"

# PHP includes
cp -r includes "${PACKAGE_DIR}/"

# Built assets
cp -r build "${PACKAGE_DIR}/"

# Assets (logo, etc.)
if [ -d "assets" ]; then
    cp -r assets "${PACKAGE_DIR}/"
fi

# Languages (if exists)
if [ -d "languages" ]; then
    cp -r languages "${PACKAGE_DIR}/"
fi

# Documentation
cp README.md "${PACKAGE_DIR}/" 2>/dev/null || true

# Create the ZIP file
echo "🗜️  Creating ZIP archive..."
cd "${BUILD_DIR}"
zip -r "${PLUGIN_SLUG}-${VERSION}.zip" "${PLUGIN_SLUG}" -x "*.DS_Store" -x "*__MACOSX*"
cd ..

# Clean up the temp directory
rm -rf "${PACKAGE_DIR}"

# Output result
ZIP_SIZE=$(du -h "${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip" | cut -f1)
echo ""
echo "✅ Package created successfully!"
echo "   📍 Location: ${BUILD_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
echo "   📊 Size: ${ZIP_SIZE}"
echo ""
echo "To install:"
echo "   1. Go to WordPress Admin → Plugins → Add New → Upload Plugin"
echo "   2. Select the ZIP file and click Install Now"
