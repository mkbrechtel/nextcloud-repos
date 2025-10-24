#!/bin/bash
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

set -e

RELEASE_STAGE="prepare-app-release"
IMAGE_NAME="nextcloud-repos:release"

echo "Building release package with REUSE compliance check..."
podman build --target "$RELEASE_STAGE" -t "$IMAGE_NAME" -f Containerfile .

echo ""
echo "Extracting release tarball..."
CONTAINER_ID=$(podman create "$IMAGE_NAME")
podman cp "$CONTAINER_ID:/repos-release.tar.gz" ./repos-release.tar.gz
podman rm "$CONTAINER_ID"

echo ""
echo "✓ Release package created successfully!"
echo "  File: repos-release.tar.gz"
echo "  Size: $(du -h repos-release.tar.gz | cut -f1)"
