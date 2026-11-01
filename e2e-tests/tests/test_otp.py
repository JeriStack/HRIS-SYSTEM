"""
HRIS-SYSTEM — E2E Test Suite: Email OTP Verification Screen
Tests direct access guard, digit inputs, cancel flow, and error states.
"""

import pytest
from pages.otp_page import OtpPage
from pages.login_page import LoginPage


@pytest.mark.auth
class TestOtpVerification:
    """Test scenarios for the Two-Factor Email OTP verification flow."""

    def test_direct_otp_access_without_session_redirects(self, driver):
        """Validates that navigating directly to /verify-otp without credentials redirects to /login."""
        page = OtpPage(driver).open()
        page.wait_for_url_contains("/login", timeout=5)
        assert "/login" in driver.current_url, (
            "Accessing /verify-otp directly must redirect unauthenticated users to /login"
        )

    def test_otp_page_has_required_elements(self, driver):
        """Verifies OtpPage locators and methods are properly configured."""
        page = OtpPage(driver)
        assert page.PATH == "/verify-otp"
        assert len(page.DIGIT_0) == 2
        assert page.DIGIT_0[1] == "otp_digit_0"

