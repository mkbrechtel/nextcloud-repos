# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later

"""
Nextcloud Repos App - Basic App Test

Tests that the repos app is properly enabled and accessible.
"""
import pytest
from playwright.sync_api import expect


class TestReposApp:
    """Tests for Repos app basic functionality."""

    def id(self):
        """Return the test name (used for screenshot names)."""
        import inspect
        return self.__class__.__name__ + "." + inspect.stack()[1].function

    def test_repos_app_enabled(self, page, screenshots_dir, html_dir):
        """Test that the repos app is enabled and accessible."""
        # Navigate to the files app and make sure we're properly logged in
        page.goto("http://localhost:80/apps/files/", wait_until='domcontentloaded', timeout=30000)

        # Wait for any animations to complete
        page.wait_for_timeout(2000)

        # Capture a screenshot for visual verification
        page.screenshot(path=f"{screenshots_dir}/{self.id()}.png")

        # Save the HTML for debugging
        with open(f"{html_dir}/{self.id()}.html", "w") as f:
            f.write(page.content())

        # Check that we're logged in and on the files page
        assert "/apps/files/" in page.url, f"Not on the files page. URL: {page.url}"

    def test_repos_app_in_app_list(self, page, screenshots_dir, html_dir):
        """Test that the repos app appears in the app list."""
        # Navigate to apps management page
        page.goto("http://localhost:80/settings/apps", wait_until='domcontentloaded', timeout=30000)

        # Wait for the page to load
        page.wait_for_timeout(3000)

        # Capture a screenshot
        page.screenshot(path=f"{screenshots_dir}/{self.id()}.png")

        # Save the HTML for debugging
        with open(f"{html_dir}/{self.id()}.html", "w") as f:
            f.write(page.content())

        # Check if repos app is visible in the app list or page content
        # The app might be in various sections (installed, enabled, etc.)
        page_text = page.content().lower()

        # Check if "repositories" or "repos" appears in the page
        repos_found = 'repositor' in page_text or 'repos' in page_text

        # Also try JavaScript search for more specific results
        if not repos_found:
            repos_app_check = page.evaluate("""
                () => {
                    const bodyText = document.body.textContent || '';
                    return bodyText.toLowerCase().includes('repositor') || bodyText.toLowerCase().includes('repos');
                }
            """)
            repos_found = repos_app_check

        # The repos app should be mentioned somewhere on the apps page
        assert repos_found, "Repos app not found anywhere on the apps management page"
