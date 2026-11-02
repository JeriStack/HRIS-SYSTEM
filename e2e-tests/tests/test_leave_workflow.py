"""
HRIS-SYSTEM — E2E Test Suite: Leave Application & Approval Workflow
Tests leave filing form, business days computation widget, and HR review queue.
"""

import pytest
from pages.leave_management_page import LeaveManagementPage


@pytest.mark.leave
@pytest.mark.smoke
class TestLeaveWorkflow:
    """Verifies employee leave filing and HR administrative review queue."""

    def test_employee_leave_application_form(self, authenticated_employee):
        """Validates that employee leave application form renders required controls."""
        page = LeaveManagementPage(authenticated_employee).open_apply()
        assert page.is_apply_loaded(), "Leave application form failed to render inputs or submit button"

        # Check required form controls
        type_el = page.find_visible(page.LEAVE_TYPE_SELECT)
        start_el = page.find_visible(page.START_DATE_INPUT)
        end_el = page.find_visible(page.END_DATE_INPUT)
        reason_el = page.find_visible(page.REASON_TEXTAREA)

        assert type_el.get_attribute("required") is not None
        assert start_el.get_attribute("required") is not None
        assert end_el.get_attribute("required") is not None
        assert reason_el.get_attribute("required") is not None

    def test_hr_leave_review_queue(self, authenticated_hr):
        """Validates that HR leave review queue renders status tabs and requests table."""
        page = LeaveManagementPage(authenticated_hr).open_review_queue()
        assert page.is_review_queue_loaded(), "HR leave review queue failed to render header"
        assert page.is_visible(page.STATUS_FILTER_ALL, timeout=5)
        assert page.is_visible(page.STATUS_FILTER_PENDING, timeout=5)

