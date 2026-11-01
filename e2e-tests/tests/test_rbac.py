"""
HRIS-SYSTEM — E2E Test Suite: Role-Based Access Control (RBAC) & Route Security
Verifies authentication enforcement, session protection, and unauthorized redirects.
"""

import pytest
from pages.base_page import BasePage


@pytest.mark.rbac
@pytest.mark.smoke
class TestRbacSecurity:
    """Validates RBAC route protections for unauthenticated sessions."""

    @pytest.mark.parametrize("protected_route", [
        "/hr/dashboard",
        "/employee/dashboard",
        "/hr/employees",
        "/hr/employees/create",
        "/hr/departments",
        "/hr/departments/create",
        "/leaves",
        "/leaves/apply",
        "/employee/profile",
    ])
    def test_unauthenticated_redirect_to_login(self, driver, protected_route):
        """Ensures all protected routes redirect anonymous guests to /login."""
        page = BasePage(driver)
        page.open(protected_route)

        # Allow redirect to complete
        page.wait_for_url_contains("/login", timeout=5)
        assert "/login" in driver.current_url, (
            f"Expected protected route {protected_route} to redirect to /login, but got {driver.current_url}"
        )

