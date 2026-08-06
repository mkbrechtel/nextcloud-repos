#!/bin/bash
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

# Setup test repositories for browser tests

CONTAINER_NAME="nextcloud-repos-dev"

echo "Setting up test repositories..."

# Create BrowserTestRepo
echo "Creating BrowserTestRepo..."
REPO_ID=$(podman exec -u www-data "$CONTAINER_NAME" php occ repos:create BrowserTestRepo | tr -d '[:space:]')
echo "Created repository with ID: $REPO_ID"

# Add admin group with full permissions
echo "Adding admin group..."
podman exec -u www-data "$CONTAINER_NAME" php occ repos:group "$REPO_ID" admin write delete share

echo "✓ Test repository setup complete!"
echo "Repository 'BrowserTestRepo' is ready for testing"
