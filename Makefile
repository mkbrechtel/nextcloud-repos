# Makefile for Nextcloud Repos App
#
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

# Variables
APP_NAME = repos
CONTAINER_NAME = nextcloud-repos-dev
DEV_IMAGE_NAME = nextcloud-repos:dev
TEST_IMAGE_NAME = nextcloud-repos:browser-test
PHP_TEST_IMAGE_NAME = nextcloud-repos:php-test
RELEASE_IMAGE_NAME = nextcloud-repos:release
RELEASE_FILE = repos-release.tar.gz

# Default target
.DEFAULT_GOAL := help

# Phony targets
.PHONY: help dev dev-start dev-stop dev-restart dev-logs dev-shell dev-status occ \
        build test test-all test-browser test-php \
        release test-release clean clean-all

##@ Development Server

dev: dev-start  ## Start development server (alias for dev-start)

dev-start:  ## Build and start the development server
	@echo "Building Nextcloud Repos development image..."
	podman build --target dev-env -t "$(DEV_IMAGE_NAME)" -f Containerfile .
	@echo "Stopping and removing existing container if running..."
	-podman stop "$(CONTAINER_NAME)" 2>/dev/null
	-podman rm "$(CONTAINER_NAME)" 2>/dev/null
	@echo "Starting Nextcloud Repos development server..."
	podman run -d \
	  --name "$(CONTAINER_NAME)" \
	  -p 127.0.0.1:8080:80 \
	  -v "$$(pwd):/var/www/html/custom_apps/repos:z" \
	  --health-cmd "curl -f http://localhost/status.php || exit 1" \
	  --health-interval 30s \
	  --health-timeout 10s \
	  --health-retries 3 \
	  --health-start-period 60s \
	  "$(DEV_IMAGE_NAME)"
	@echo ""
	@echo "Enabling repos app..."
	@sleep 5
	podman exec -u www-data "$(CONTAINER_NAME)" php occ app:enable repos
	@echo ""
	@echo "✓ Development server started successfully!"
	@echo "  Container: $(CONTAINER_NAME)"
	@echo "  URL: http://localhost:8080"
	@echo "  Admin credentials: admin / admin"
	@echo ""
	@echo "Useful commands:"
	@echo "  make dev-logs    - View logs"
	@echo "  make dev-shell   - Open shell"
	@echo "  make dev-stop    - Stop server"
	@echo "  make occ ARGS='repos:list' - Run occ commands"

dev-stop:  ## Stop the development server
	@echo "Stopping development server..."
	podman stop "$(CONTAINER_NAME)"

dev-restart:  ## Restart the development server
	@echo "Restarting development server..."
	podman restart "$(CONTAINER_NAME)"

dev-logs:  ## Show development server logs (follow mode)
	podman logs -f "$(CONTAINER_NAME)"

dev-shell:  ## Open a shell in the development container
	podman exec -it -u www-data "$(CONTAINER_NAME)" bash

dev-status:  ## Show development server status
	@podman ps -f name="$(CONTAINER_NAME)"

##@ OCC Commands

occ:  ## Run occ commands (use: make occ ARGS="repos:list")
	@if ! podman ps --format "{{.Names}}" | grep -q "^$(CONTAINER_NAME)$$"; then \
	    echo "Error: Container '$(CONTAINER_NAME)' is not running."; \
	    echo "Start it with: make dev-start"; \
	    exit 1; \
	fi
	podman exec -u www-data "$(CONTAINER_NAME)" php occ $(ARGS)

##@ Testing

test: test-php test-browser  ## Run all tests (PHP unit tests + browser tests)

test-all: test  ## Alias for 'test'

test-browser:  ## Run browser tests with Playwright
	@if ! podman ps --format "{{.Names}}" | grep -q "^$(CONTAINER_NAME)$$"; then \
	    echo "Error: Development server '$(CONTAINER_NAME)' is not running."; \
	    echo "Start it with: make dev-start"; \
	    exit 1; \
	fi
	@echo "Building browser test image..."
	podman build --target browser-test -t "$(TEST_IMAGE_NAME)" -f Containerfile .
	@echo "Running browser tests..."
	podman run --rm \
	    --name nextcloud-repos-browser-test \
	    --network container:$(CONTAINER_NAME) \
	    -v "$$(pwd)/tests:/var/www/html/custom_apps/repos/tests:z" \
	    "$(TEST_IMAGE_NAME)" \
	    sh -c "cd tests/browser && pytest -v"
	@echo ""
	@echo "✓ Browser tests completed!"
	@echo "  Screenshots: tests/browser/screenshots/"
	@echo "  HTML output: tests/browser/html_output/"

test-php:  ## Run PHP unit tests with PHPUnit
	@echo "Building PHP test image..."
	podman build --target php-test -t "$(PHP_TEST_IMAGE_NAME)" -f Containerfile .
	@echo "Running PHP unit tests..."
	podman run --rm \
	    --name nextcloud-repos-php-test \
	    "$(PHP_TEST_IMAGE_NAME)"
	@echo ""
	@echo "✓ PHP unit tests completed!"

##@ Build & Release

build:  ## Build frontend assets (JavaScript/CSS) using Containerfile
	@echo "Building frontend assets using Containerfile..."
	podman build --target frontend-build -t localhost/nextcloud-repos:frontend-build -f Containerfile .
	@echo "Extracting built assets..."
	-rm -rf js/
	@CONTAINER_ID=$$(podman create localhost/nextcloud-repos:frontend-build) && \
	podman cp "$$CONTAINER_ID:/build/js" ./js && \
	podman rm "$$CONTAINER_ID"
	@echo "✓ Frontend build completed!"
	@echo "  Output: js/"

release:  ## Build release tarball with REUSE compliance check
	@echo "Building release package with REUSE compliance check..."
	podman build --target prepare-app-release -t "$(RELEASE_IMAGE_NAME)" -f Containerfile .
	@echo ""
	@echo "Extracting release tarball..."
	$(eval CONTAINER_ID := $(shell podman create "$(RELEASE_IMAGE_NAME)"))
	podman cp "$(CONTAINER_ID):/repos-release.tar.gz" ./$(RELEASE_FILE)
	podman rm "$(CONTAINER_ID)"
	@echo ""
	@echo "✓ Release package created successfully!"
	@echo "  File: $(RELEASE_FILE)"
	@echo "  Size: $$(du -h $(RELEASE_FILE) | cut -f1)"

test-release:  ## Test the release tarball
	@if [ ! -f "$(RELEASE_FILE)" ]; then \
	    echo "Error: $(RELEASE_FILE) not found. Run 'make release' first."; \
	    exit 1; \
	fi
	@echo "Testing release package..."
	@echo ""
	$(eval TEST_DIR := $(shell mktemp -d))
	@echo "Extracting to: $(TEST_DIR)"
	@tar -xzf "$(RELEASE_FILE)" -C "$(TEST_DIR)"
	@echo ""
	@echo "Checking required files..."
	@for item in appinfo/info.xml lib/AppInfo/Application.php js/repos-files.js js/repos-init.js js/repos-sharing.js l10n/de.js templates LICENSES/AGPL-3.0-or-later.txt; do \
	    if [ -e "$(TEST_DIR)/$$item" ]; then \
	        echo "✓ $$item"; \
	    else \
	        echo "✗ MISSING: $$item"; \
	        exit 1; \
	    fi \
	done
	@echo ""
	@echo "Checking for unwanted files..."
	@for item in node_modules src tests .git .github package.json yarn.lock tsconfig.json .gitignore; do \
	    if [ -e "$(TEST_DIR)/$$item" ]; then \
	        echo "✗ FOUND (should not be in release): $$item"; \
	        exit 1; \
	    else \
	        echo "✓ $$item (correctly excluded)"; \
	    fi \
	done
	@echo ""
	@echo "Contents summary:"
	@du -sh "$(TEST_DIR)"
	@find "$(TEST_DIR)" -type f | wc -l | xargs echo "Total files:"
	@rm -rf "$(TEST_DIR)"
	@echo ""
	@echo "✓ Release package test PASSED"

##@ Cleanup

clean:  ## Clean build artifacts and containers
	@echo "Cleaning build artifacts..."
	-rm -f $(RELEASE_FILE)
	-rm -rf js/
	-rm -rf node_modules/
	-podman stop "$(CONTAINER_NAME)" 2>/dev/null || true
	-podman rm "$(CONTAINER_NAME)" 2>/dev/null || true
	@echo "✓ Cleanup completed!"

clean-all: clean  ## Clean everything including all Docker images
	@echo "Removing all related images..."
	-podman rmi "$(DEV_IMAGE_NAME)" 2>/dev/null || true
	-podman rmi "$(TEST_IMAGE_NAME)" 2>/dev/null || true
	-podman rmi "$(PHP_TEST_IMAGE_NAME)" 2>/dev/null || true
	-podman rmi "$(RELEASE_IMAGE_NAME)" 2>/dev/null || true
	@echo "✓ All images removed!"

##@ Help

help:  ## Display this help message
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
