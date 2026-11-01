"""
HRIS-SYSTEM — E2E Test Suite: Authentication & Login
Tests login UI components, password toggle, validation, and error handling.
"""

import pytest
from config.settings import settings
from pages.login_page import LoginPage


@pytest.mark.auth
@pytest.mark.smoke
class TestLogin:
    """Test scenarios for the Sign-In screen."""

    def test_login_page_renders_correctly(self, driver):
        """Validates that login page elements render and are accessible."""
        page = LoginPage(driver).open()
        assert page.is_login_page_loaded(), "Login page failed to load essential elements"
        assert "Sign In" in driver.title or "HRIS" in driver.title

    def test_toggle_password_visibility(self, driver):
        """Validates that clicking the password toggle switches input type between password and text."""
        page = LoginPage(driver).open()
        assert page.get_password_field_type() == "password", "Initial password input type must be 'password'"

        page.toggle_password_visibility()
        assert page.get_password_field_type() == "text", "Password input type must be 'text' after toggle"

        page.toggle_password_visibility()
        assert page.get_password_field_type() == "password", "Password input type must return to 'password' after second toggle"

    def test_login_with_empty_credentials(self, driver):
        """Validates client-side validation prevents submission when fields are empty."""
        page = LoginPage(driver).open().ensure_modal_open()
        email_el = page.find_visible(page.EMAIL_INPUT)
        assert email_el.get_attribute("required") is not None, "Email field should be marked required"

        page.click_sign_in()
        # Page should remain on /login
        assert "/login" in driver.current_url

    def test_login_with_invalid_credentials(self, driver):
        """Validates error message displayed when submitting invalid credentials."""
        page = LoginPage(driver).open()
        page.login("nonexistent.user@company.local", "WrongPassword123!")

        error = page.get_error_message()
        assert error != "", "Expected error banner for invalid credentials"
        assert any(term in error.lower() for term in ["invalid", "incorrect", "credential", "failed", "unable"]), (
            f"Unexpected error message: '{error}'"
        )

