#!/bin/bash
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

CONTAINER_NAME="nextcloud-repos-dev"
TEST_IMAGE_NAME="nextcloud-repos:test"
TEST_CONTAINER_NAME="nextcloud-repos-test"

# Check if dev server is running
if ! podman ps --format "{{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
    echo "Error: Development server '${CONTAINER_NAME}' is not running."
    echo "Start it with: ./start-dev-server.sh"
    exit 1
fi

echo "Building test image..."
podman build --target test-env -t "$TEST_IMAGE_NAME" -f Containerfile .

echo "Running tests..."
podman run --rm \
    --name "$TEST_CONTAINER_NAME" \
    --network container:$CONTAINER_NAME \
    -v "$(pwd)/tests:/var/www/html/custom_apps/repos/tests:z" \
    "$TEST_IMAGE_NAME" \
    sh -c "cd tests/browser && pytest -v"

echo ""
echo "✓ Tests completed!"
echo "  Screenshots: tests/browser/screenshots/"
echo "  HTML output: tests/browser/html_output/"
