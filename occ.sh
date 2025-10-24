#!/bin/bash
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

CONTAINER_NAME="nextcloud-repos-dev"

# Check if container is running
if ! podman ps --format "{{.Names}}" | grep -q "^${CONTAINER_NAME}$"; then
    echo "Error: Container '${CONTAINER_NAME}' is not running."
    echo "Start it with: ./start-dev-server.sh"
    exit 1
fi

# Run occ command
podman exec -u www-data "$CONTAINER_NAME" php occ "$@"
