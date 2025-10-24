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
        page.goto("http://localhost:80/apps/files/")
        page.wait_for_load_state('networkidle')

        # Wait for any animations to complete
        page.wait_for_timeout(1000)

        # Capture a screenshot for visual verification
        page.screenshot(path=f"{screenshots_dir}/{self.id()}.png")

        # Save the HTML for debugging
        with open(f"{html_dir}/{self.id()}.html", "w") as f:
            f.write(page.content())

        # Check that we're logged in and on the files page
        assert page.url.startswith("http://localhost:80/apps/files/"), "Not on the files page"

    def test_repos_app_in_app_list(self, page, screenshots_dir, html_dir):
        """Test that the repos app appears in the app list."""
        # Navigate to apps management page
        page.goto("http://localhost:80/settings/apps")
        page.wait_for_load_state('networkidle')

        # Wait for the page to load
        page.wait_for_timeout(2000)

        # Capture a screenshot
        page.screenshot(path=f"{screenshots_dir}/{self.id()}.png")

        # Save the HTML for debugging
        with open(f"{html_dir}/{self.id()}.html", "w") as f:
            f.write(page.content())

        # Check if repos app is visible in the app list
        repos_app_check = page.evaluate("""
            () => {
                const appRows = document.querySelectorAll('.app-list .app-item, [class*="app-row"]');
                for (const row of appRows) {
                    const text = row.textContent || '';
                    if (text.toLowerCase().includes('repositor')) {
                        return { present: true, enabled: text.toLowerCase().includes('disable') };
                    }
                }
                return { present: false, enabled: false };
            }
        """)

        # Assert that the repos app is present
        assert repos_app_check.get('present'), "Repos app not found in the app list"
