"""
HRIS-SYSTEM — E2E Test Suite: Employee Dashboard
Tests Employee self-service widgets, leave balance cards, navigation, and sign-out.
"""

import pytest
from pages.employee_dashboard_page import EmployeeDashboardPage
from pages.login_page import LoginPage


@pytest.mark.dashboard
@pytest.mark.smoke
class TestEmployeeDashboard:
    """Verifies self-service widgets and balance indicators on the Employee Dashboard."""

    def test_employee_dashboard_renders(self, authenticated_employee):
        """Validates that Employee portal renders welcome banner and balance cards."""
        page = EmployeeDashboardPage(authenticated_employee)
        assert page.is_loaded(), "Employee Dashboard failed to render welcome banner or employee badge"
        assert "Welcome" in page.get_welcome_text()

        # Check balance cards
        balance_types = page.get_leave_balance_types()
        assert len(balance_types) >= 1, "Expected employee leave balance cards to be rendered"

    def test_employee_navigation_to_file_leave(self, authenticated_employee):
        """Verifies clicking File Leave routes to /leaves/apply."""
        page = EmployeeDashboardPage(authenticated_employee)
        page.click_file_leave()
        page.wait_for_url_contains("/leaves/apply", timeout=5)
        assert "/leaves/apply" in authenticated_employee.current_url

    def test_employee_sign_out(self, authenticated_employee):
        """Verifies employee sign-out terminates the session and returns to /login."""
        page = EmployeeDashboardPage(authenticated_employee)
        page.click_sign_out()
        page.wait_for_url_contains("/login", timeout=5)
        assert "/login" in authenticated_employee.current_url
        assert LoginPage(authenticated_employee).is_login_page_loaded()

