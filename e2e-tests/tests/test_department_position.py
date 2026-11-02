"""
HRIS-SYSTEM — E2E Test Suite: Department Management
Tests department directory listing and new department creation form.
"""

import pytest
from pages.department_page import DepartmentPage


@pytest.mark.department
class TestDepartmentManagement:
    """Verifies organizational departments views and registration forms."""

    def test_department_directory_renders(self, authenticated_hr):
        """Validates department directory table and configuration cards load properly."""
        page = DepartmentPage(authenticated_hr).open()
        assert page.is_loaded(), "Departments directory failed to load header or add button"

        names = page.get_department_names()
        assert len(names) >= 1, "Expected at least 1 department configured in directory"

    def test_create_department_form_renders(self, authenticated_hr):
        """Validates navigation to and rendering of the new department form."""
        page = DepartmentPage(authenticated_hr).open_create()
        assert page.is_create_form_loaded(), "Create Department form failed to render inputs"

        name_input = page.find_visible(page.DEPT_NAME_INPUT)
        assert name_input.get_attribute("required") is not None, "Department name must be required"

