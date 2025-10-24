#!/bin/bash
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

TEST_IMAGE_NAME="nextcloud-repos:php-test"
TEST_CONTAINER_NAME="nextcloud-repos-php-test"

echo "Building PHP test image..."
podman build --target php-test -t "$TEST_IMAGE_NAME" -f Containerfile .

echo "Running PHP unit tests..."
podman run --rm \
    --name "$TEST_CONTAINER_NAME" \
    "$TEST_IMAGE_NAME"

echo ""
echo "✓ PHP unit tests completed!"
