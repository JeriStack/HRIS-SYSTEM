"""
HRIS-SYSTEM — Login Page Object
Models /login page interactions, credential entry, password toggling, and alerts.
"""

from typing import Tuple
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class LoginPage(BasePage):
    """Page Object for the HRIS Sign-In screen."""

    PATH = "/login"

    # Locators matching actual HRIS DOM
    EMAIL_INPUT: Tuple[By, str] = (By.ID, "loginEmail")
    PASSWORD_INPUT: Tuple[By, str] = (By.ID, "loginPassword")
    SUBMIT_BUTTON: Tuple[By, str] = (By.ID, "loginSubmitBtn")
    TOGGLE_PASSWORD_BTN: Tuple[By, str] = (By.ID, "togglePasswordBtn")
    CONTINUE_AS_HR_BTN: Tuple[By, str] = (By.ID, "continueAsHrBtn")
    CONTINUE_AS_EMP_BTN: Tuple[By, str] = (By.ID, "continueAsEmployeeBtn")
    MODAL_BACKDROP: Tuple[By, str] = (By.ID, "loginModalBackdrop")
    BRAND_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".login-brand h1")
    ALERT_ERROR: Tuple[By, str] = (By.CSS_SELECTOR, ".alert-error")
    ALERT_BANNER: Tuple[By, str] = (By.CSS_SELECTOR, ".alert-banner")
    BACK_LINK: Tuple[By, str] = (By.CSS_SELECTOR, ".back-link")

    def open(self) -> "LoginPage":
        super().open(self.PATH)
        return self

    def ensure_modal_open(self, role: str = "") -> "LoginPage":
        """Opens the login modal via role selection card if not already open."""
        if not self.is_visible(self.EMAIL_INPUT, timeout=1):
            btn_locator = (
                self.CONTINUE_AS_EMP_BTN
                if role.lower() == "employee"
                else self.CONTINUE_AS_HR_BTN
            )
            if self.is_visible(btn_locator, timeout=3):
                self.click(btn_locator)
        return self

    def select_role_portal(self, role: str = "hr") -> "LoginPage":
        """Selects the HR or Employee role card to open the login modal."""
        btn_locator = (
            self.CONTINUE_AS_EMP_BTN
            if role.lower() == "employee"
            else self.CONTINUE_AS_HR_BTN
        )
        if self.is_visible(btn_locator, timeout=3):
            self.click(btn_locator)
        return self

    def enter_email(self, email: str) -> "LoginPage":
        self.ensure_modal_open()
        self.type(self.EMAIL_INPUT, email)
        return self

    def enter_password(self, password: str) -> "LoginPage":
        self.ensure_modal_open()
        self.type(self.PASSWORD_INPUT, password)
        return self

    def click_sign_in(self) -> None:
        self.ensure_modal_open()
        self.click(self.SUBMIT_BUTTON)

    def login(self, email: str, password: str, role: str = "") -> None:
        """Helper to fill credentials and submit form."""
        if role:
            self.select_role_portal(role)
        else:
            self.ensure_modal_open()
        self.enter_email(email)
        self.enter_password(password)
        self.click_sign_in()

    def toggle_password_visibility(self) -> "LoginPage":
        self.ensure_modal_open()
        self.click(self.TOGGLE_PASSWORD_BTN)
        return self

    def get_password_field_type(self) -> str:
        self.ensure_modal_open()
        return self.find_visible(self.PASSWORD_INPUT).get_attribute("type") or ""

    def get_error_message(self) -> str:
        if self.is_visible(self.ALERT_ERROR, timeout=3):
            return self.get_text(self.ALERT_ERROR)
        if self.is_visible(self.ALERT_BANNER, timeout=3):
            return self.get_text(self.ALERT_BANNER)
        return ""

    def is_login_page_loaded(self) -> bool:
        if self.is_visible(self.CONTINUE_AS_HR_BTN, timeout=2) and self.is_visible(self.CONTINUE_AS_EMP_BTN, timeout=2):
            return True
        return self.is_visible(self.EMAIL_INPUT) and self.is_visible(self.SUBMIT_BUTTON)

