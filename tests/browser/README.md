# Nextcloud Repos App Browser Tests

This directory contains integration tests for the Nextcloud Repos App using Playwright.

## Prerequisites

- Nextcloud development environment up and running on http://localhost:8080
- Repos app enabled:
  ```
  ./occ.sh app:enable repos
  ```

## Running Tests

### From the test container:

1. Build and run the test container:
   ```
   ./run-tests.sh
   ```

### From your host (if you have Python/Playwright installed):

1. Install Python dependencies:
   ```
   pip install -r requirements.txt
   ```

2. Install Playwright browsers:
   ```
   playwright install
   ```

3. Run the tests:
   ```
   cd tests/browser
   pytest -v
   ```

## Test Structure

- `conftest.py` - Pytest configuration and fixtures
- `test_app_enabled.py` - Tests for basic app functionality

## Screenshots

Tests automatically take screenshots which are saved to the `screenshots/` directory.
HTML output is saved to the `html_output/` directory.
