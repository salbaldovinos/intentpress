#!/bin/bash
#
# Bump version for IntentPress plugin
#
# Usage:
#   ./scripts/bump-version.sh <version>     # Set specific version (e.g., 0.2.0)
#   ./scripts/bump-version.sh patch         # Bump patch version (0.1.0 -> 0.1.1)
#   ./scripts/bump-version.sh minor         # Bump minor version (0.1.0 -> 0.2.0)
#   ./scripts/bump-version.sh major         # Bump major version (0.1.0 -> 1.0.0)
#

set -e

# Get current version from package.json
CURRENT_VERSION=$(node -p "require('./package.json').version")

# Parse current version
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"

# Determine new version
if [ -z "$1" ]; then
    echo "Usage: $0 <version|patch|minor|major>"
    echo ""
    echo "Current version: $CURRENT_VERSION"
    echo ""
    echo "Examples:"
    echo "  $0 0.2.0    # Set specific version"
    echo "  $0 patch    # $CURRENT_VERSION -> $MAJOR.$MINOR.$((PATCH + 1))"
    echo "  $0 minor    # $CURRENT_VERSION -> $MAJOR.$((MINOR + 1)).0"
    echo "  $0 major    # $CURRENT_VERSION -> $((MAJOR + 1)).0.0"
    exit 1
fi

case "$1" in
    patch)
        NEW_VERSION="$MAJOR.$MINOR.$((PATCH + 1))"
        ;;
    minor)
        NEW_VERSION="$MAJOR.$((MINOR + 1)).0"
        ;;
    major)
        NEW_VERSION="$((MAJOR + 1)).0.0"
        ;;
    *)
        # Validate version format
        if [[ ! "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
            echo "❌ Invalid version format: $1"
            echo "   Expected format: X.Y.Z (e.g., 0.2.0)"
            exit 1
        fi
        NEW_VERSION="$1"
        ;;
esac

echo "📦 Bumping version: $CURRENT_VERSION → $NEW_VERSION"
echo ""

# Update package.json
echo "   Updating package.json..."
sed -i '' "s/\"version\": \"$CURRENT_VERSION\"/\"version\": \"$NEW_VERSION\"/" package.json

# Update composer.json
echo "   Updating composer.json..."
sed -i '' "s/\"version\": \"$CURRENT_VERSION\"/\"version\": \"$NEW_VERSION\"/" composer.json

# Update intentpress.php header
echo "   Updating intentpress.php header..."
sed -i '' "s/\* Version:           $CURRENT_VERSION/* Version:           $NEW_VERSION/" intentpress.php

# Update intentpress.php constant
echo "   Updating intentpress.php constant..."
sed -i '' "s/define( 'INTENTPRESS_VERSION', '$CURRENT_VERSION' );/define( 'INTENTPRESS_VERSION', '$NEW_VERSION' );/" intentpress.php

echo ""
echo "✅ Version bumped to $NEW_VERSION"
echo ""
echo "Files updated:"
echo "   - package.json"
echo "   - composer.json"
echo "   - intentpress.php"
echo ""
echo "Next steps:"
echo "   npm run package    # Build and create ZIP"
