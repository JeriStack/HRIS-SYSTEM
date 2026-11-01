"""
HRIS-SYSTEM — E2E Test Suite: Employee Management
Tests employee directory view, search filtering, and new employee registration form.
"""

import pytest
from pages.employee_management_page import EmployeeManagementPage


@pytest.mark.employee
@pytest.mark.smoke
class TestEmployeeManagement:
    """Verifies employee directory interactions and registration forms."""

    def test_employee_directory_renders(self, authenticated_hr):
        """Validates employee directory table and action buttons load properly."""
        page = EmployeeManagementPage(authenticated_hr).open()
        assert page.is_directory_loaded(), "Employee directory failed to render search controls or add button"
        assert page.get_employee_row_count() >= 1, "Expected employee rows in directory table"

    def test_employee_search_filter(self, authenticated_hr):
        """Validates that search bar filters the employee directory table."""
        page = EmployeeManagementPage(authenticated_hr).open()
        initial_count = page.get_employee_row_count()
        assert initial_count > 0, "Initial directory table must have records"

        # Search for seeded employee Marcus
        search_term = "Marcus"
        page.search_employee(search_term)
        filtered_count = page.get_employee_row_count()
        assert filtered_count >= 1, f"Expected at least 1 record for search term '{search_term}'"
        assert search_term.lower() in authenticated_hr.page_source.lower()

    def test_create_employee_form_renders(self, authenticated_hr):
        """Validates navigation to and rendering of the new employee registration form."""
        page = EmployeeManagementPage(authenticated_hr).open_create()
        assert page.is_create_form_loaded(), "Create Employee form failed to load inputs"

        # Verify HTML5 required attributes on mandatory fields
        emp_no = page.find_visible(page.EMP_NO_INPUT)
        first_name = page.find_visible(page.FIRST_NAME_INPUT)
        last_name = page.find_visible(page.LAST_NAME_INPUT)
        email = page.find_visible(page.EMAIL_INPUT)

        assert emp_no.get_attribute("required") is not None
        assert first_name.get_attribute("required") is not None
        assert last_name.get_attribute("required") is not None
        assert email.get_attribute("required") is not None

