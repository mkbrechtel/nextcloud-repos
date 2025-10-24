#!/bin/bash
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

RELEASE_FILE="repos-release.tar.gz"

if [ ! -f "$RELEASE_FILE" ]; then
    echo "Error: $RELEASE_FILE not found. Run ./build-release.sh first."
    exit 1
fi

echo "Testing release package..."
echo ""

# Create temporary directory for testing
TEST_DIR=$(mktemp -d)
echo "Extracting to: $TEST_DIR"
tar -xzf "$RELEASE_FILE" -C "$TEST_DIR"

echo ""
echo "Checking required files..."

# List of required files/directories
REQUIRED_ITEMS=(
    "appinfo/info.xml"
    "lib/AppInfo/Application.php"
    "js/repos-files.js"
    "js/repos-init.js"
    "js/repos-sharing.js"
    "l10n/de.js"
    "templates"
    "LICENSES/AGPL-3.0-or-later.txt"
)

ALL_FOUND=true
for item in "${REQUIRED_ITEMS[@]}"; do
    if [ -e "$TEST_DIR/$item" ]; then
        echo "✓ $item"
    else
        echo "✗ MISSING: $item"
        ALL_FOUND=false
    fi
done

echo ""
echo "Checking for unwanted files..."

# Files that should NOT be in the release
UNWANTED_ITEMS=(
    "node_modules"
    "src"
    "tests"
    ".git"
    ".github"
    "package.json"
    "yarn.lock"
    "tsconfig.json"
    ".gitignore"
)

NONE_FOUND=true
for item in "${UNWANTED_ITEMS[@]}"; do
    if [ -e "$TEST_DIR/$item" ]; then
        echo "✗ FOUND (should not be in release): $item"
        NONE_FOUND=false
    else
        echo "✓ $item (correctly excluded)"
    fi
done

echo ""
echo "Contents summary:"
du -sh "$TEST_DIR"
find "$TEST_DIR" -type f | wc -l | xargs echo "Total files:"

echo ""
echo "Directory structure:"
tree -L 2 "$TEST_DIR" 2>/dev/null || find "$TEST_DIR" -maxdepth 2 -type d

# Cleanup
rm -rf "$TEST_DIR"

echo ""
if [ "$ALL_FOUND" = true ] && [ "$NONE_FOUND" = true ]; then
    echo "✓ Release package test PASSED"
    exit 0
else
    echo "✗ Release package test FAILED"
    exit 1
fi
