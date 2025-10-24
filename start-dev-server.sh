#!/bin/bash
set -e

CONTAINER_NAME="nextcloud-repos-dev"
IMAGE_NAME="nextcloud-repos:dev"

echo "Building Nextcloud Repos development image..."
podman build --target dev-env -t "$IMAGE_NAME" -f Containerfile .

echo "Stopping and removing existing container if running..."
podman stop "$CONTAINER_NAME" 2>/dev/null || true
podman rm "$CONTAINER_NAME" 2>/dev/null || true

echo "Starting Nextcloud Repos development server..."
podman run -d \
  --name "$CONTAINER_NAME" \
  -p 127.0.0.1:8080:80 \
  -v "$(pwd):/var/www/html/custom_apps/repos:z" \
  --health-cmd "curl -f http://localhost/status.php || exit 1" \
  --health-interval 30s \
  --health-timeout 10s \
  --health-retries 3 \
  --health-start-period 60s \
  "$IMAGE_NAME"

echo ""
echo "Enabling repos app..."
sleep 5  # Wait for container to be ready
podman exec -u www-data "$CONTAINER_NAME" php occ app:enable repos

echo ""
echo "✓ Development server started successfully!"
echo "  Container: $CONTAINER_NAME"
echo "  URL: http://localhost:8080"
echo "  Admin credentials: admin / admin"
echo ""
echo "To view logs: podman logs -f $CONTAINER_NAME"
echo "To stop: podman stop $CONTAINER_NAME"
