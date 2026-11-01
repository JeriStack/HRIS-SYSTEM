"""
HRIS-SYSTEM — E2E Test Suite: HR Dashboard
Tests HR dashboard widgets, metrics overview, sidebar navigation, and session sign-out.
"""

import pytest
from pages.hr_dashboard_page import HrDashboardPage
from pages.login_page import LoginPage


@pytest.mark.dashboard
@pytest.mark.smoke
class TestHrDashboard:
    """Verifies operational widgets and navigation on the HR Dashboard."""

    def test_hr_dashboard_renders_metrics(self, authenticated_hr):
        """Validates that HR portal renders header, HR role badge, and metric cards."""
        page = HrDashboardPage(authenticated_hr)
        assert page.is_loaded(), "HR Dashboard failed to render header or HR badge"
        assert "Human Resources" in page.get_header_text()

        # Check stat values are populated
        stats = page.get_stat_values()
        assert len(stats) >= 1, "Expected at least 1 statistical metric on HR dashboard"

    def test_hr_navigation_to_employees(self, authenticated_hr):
        """Verifies clicking Employees navigation link routes to /hr/employees."""
        page = HrDashboardPage(authenticated_hr)
        page.click_employees_directory()
        page.wait_for_url_contains("/hr/employees", timeout=5)
        assert "/hr/employees" in authenticated_hr.current_url

    def test_hr_navigation_to_departments(self, authenticated_hr):
        """Verifies clicking Departments navigation link routes to /hr/departments."""
        page = HrDashboardPage(authenticated_hr)
        page.click_departments()
        page.wait_for_url_contains("/hr/departments", timeout=5)
        assert "/hr/departments" in authenticated_hr.current_url

    def test_hr_sign_out(self, authenticated_hr):
        """Verifies clicking Sign Out terminates the session and returns to /login."""
        page = HrDashboardPage(authenticated_hr)
        page.click_sign_out()
        page.wait_for_url_contains("/login", timeout=5)
        assert "/login" in authenticated_hr.current_url
        assert LoginPage(authenticated_hr).is_login_page_loaded()

