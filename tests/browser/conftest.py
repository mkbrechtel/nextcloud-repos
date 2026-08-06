# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

"""
Pytest configuration for Nextcloud Repos App Playwright tests
"""
import os
import pytest
from pathlib import Path
from playwright.sync_api import sync_playwright

# Define fixtures for test setup/teardown
@pytest.fixture(scope="session")
def browser_type():
    """Launch Playwright and return a browser type."""
    with sync_playwright() as p:
        yield p.chromium

@pytest.fixture
def browser(browser_type):
    """Launch a browser instance."""
    browser = browser_type.launch(headless=True)
    yield browser
    browser.close()

@pytest.fixture
def context(browser):
    """Create a new browser context."""
    # Note: We access port 80 because the test container shares the network namespace
    # with the dev container via --network container:nextcloud-repos-dev
    context = browser.new_context(
        base_url="http://localhost:80"
    )

    # Create a page for login
    page = context.new_page()

    try:
        # Navigate to Nextcloud
        page.goto("http://localhost:80", wait_until="domcontentloaded", timeout=30000)

        # Wait for page to be ready
        page.wait_for_timeout(2000)

        # Check if we're already logged in or need to login
        try:
            # Try to find the login form
            if page.locator('input[name="user"]').is_visible(timeout=5000):
                # Fill in credentials
                page.locator('input[name="user"]').fill("admin")
                page.locator('input[name="password"]').fill("admin")

                # Wait a moment
                page.wait_for_timeout(500)

                # Submit the form by pressing Enter
                with page.expect_navigation(timeout=30000, wait_until="domcontentloaded"):
                    page.locator('input[name="password"]').press("Enter")

                # Wait for dashboard to load
                page.wait_for_timeout(2000)
        except Exception as e:
            print(f"Login attempt info: {str(e)}")

        # Wait for the header to appear (indicates page is ready)
        page.wait_for_selector('#header', timeout=10000)

        # Store authentication state
        storage_state = context.storage_state()

        # Close the login page
        page.close()

    except Exception as e:
        # Save debug info before failing
        try:
            page.screenshot(path="/tmp/login-failure.png")
            with open("/tmp/login-failure.html", "w") as f:
                f.write(page.content())
            print(f"Login failed. Debug files saved: /tmp/login-failure.png, /tmp/login-failure.html")
            print(f"Current URL: {page.url}")
        except:
            pass
        raise

    yield context
    context.close()

@pytest.fixture
def page(context):
    """Create a new page in the browser context."""
    page = context.new_page()
    yield page

@pytest.fixture
def screenshots_dir():
    """Ensure screenshots directory exists."""
    dir_path = os.path.join(os.path.dirname(__file__), "screenshots")
    os.makedirs(dir_path, exist_ok=True)
    return dir_path

@pytest.fixture
def html_dir():
    """Ensure HTML output directory exists."""
    dir_path = os.path.join(os.path.dirname(__file__), "html_output")
    os.makedirs(dir_path, exist_ok=True)
    return dir_path


# Hook to capture screenshots and HTML for all tests, including failures
@pytest.hookimpl(tryfirst=True, hookwrapper=True)
def pytest_runtest_makereport(item, call):
    # Execute the hook
    outcome = yield
    report = outcome.get_result()

    # Get test case info
    test_name = item.nodeid.replace("::", "-").replace("/", "-").replace(".", "-")

    # Only do this for actual test calls (not setup/teardown)
    if report.when == "call" or (report.when == "setup" and report.outcome != "passed"):
        # Get page fixture if available
        page = None
        for fixture_name in item._fixtureinfo.argnames:
            if fixture_name == "page" and fixture_name in item.funcargs:
                page = item.funcargs[fixture_name]
                break

        if page is not None:
            # Create output dirs if they don't exist
            screenshots_dir = Path(os.path.dirname(__file__)) / "screenshots"
            html_dir = Path(os.path.dirname(__file__)) / "html_output"
            screenshots_dir.mkdir(exist_ok=True)
            html_dir.mkdir(exist_ok=True)

            # Create status-specific filenames
            screenshot_path = screenshots_dir / f"{test_name}.png"
            html_path = html_dir / f"{test_name}.html"

            # Capture screenshot and HTML
            try:
                page.screenshot(path=str(screenshot_path))
                with open(str(html_path), 'w', encoding='utf-8') as f:
                    f.write(page.content())
            except Exception as e:
                print(f"Error capturing artifacts: {e}")
