"""
HRIS-SYSTEM — Email OTP Verification Page Object
Models /verify-otp screen interactions, digit entry, submission, and resend.
"""

from typing import Tuple
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class OtpPage(BasePage):
    """Page Object for the Two-Factor Email OTP verification screen."""

    PATH = "/verify-otp"

    # Locators
    DIGIT_0: Tuple[By, str] = (By.ID, "otp_digit_0")
    DIGIT_1: Tuple[By, str] = (By.ID, "otp_digit_1")
    DIGIT_2: Tuple[By, str] = (By.ID, "otp_digit_2")
    DIGIT_3: Tuple[By, str] = (By.ID, "otp_digit_3")
    DIGIT_4: Tuple[By, str] = (By.ID, "otp_digit_4")
    DIGIT_5: Tuple[By, str] = (By.ID, "otp_digit_5")

    OTP_HIDDEN_INPUT: Tuple[By, str] = (By.ID, "otpFullValue")
    SUBMIT_BUTTON: Tuple[By, str] = (By.ID, "verifySubmitBtn")
    RESEND_BUTTON: Tuple[By, str] = (By.ID, "resendBtn")
    CANCEL_FORM: Tuple[By, str] = (By.ID, "cancelForm")
    ALERT_ERROR: Tuple[By, str] = (By.CSS_SELECTOR, ".alert-error")
    ALERT_BANNER: Tuple[By, str] = (By.CSS_SELECTOR, ".alert-banner")

    def open(self) -> "OtpPage":
        super().open(self.PATH)
        return self

    def is_otp_page_loaded(self) -> bool:
        return self.is_visible(self.DIGIT_0, timeout=5) and self.is_visible(self.SUBMIT_BUTTON, timeout=5)

    def enter_otp(self, code: str) -> "OtpPage":
        """Enters a 6-digit OTP across the split input boxes."""
        digits = [self.DIGIT_0, self.DIGIT_1, self.DIGIT_2, self.DIGIT_3, self.DIGIT_4, self.DIGIT_5]
        clean_code = code.strip()

        for idx, char in enumerate(clean_code[:6]):
            if idx < len(digits):
                self.type(digits[idx], char, clear=True)

        return self

    def click_verify(self) -> None:
        self.click(self.SUBMIT_BUTTON)

    def submit_otp(self, code: str) -> None:
        self.enter_otp(code)
        # Note: Frontend auto-submits once 6th digit is typed. Click only if still present.
        try:
            if self.is_visible(self.SUBMIT_BUTTON, timeout=1):
                self.click(self.SUBMIT_BUTTON)
        except Exception:
            pass


    def get_error_message(self) -> str:
        if self.is_visible(self.ALERT_ERROR, timeout=3):
            return self.get_text(self.ALERT_ERROR)
        if self.is_visible(self.ALERT_BANNER, timeout=3):
            return self.get_text(self.ALERT_BANNER)
        return ""

    def is_resend_disabled(self) -> bool:
        element = self.find_visible(self.RESEND_BUTTON)
        return element.get_attribute("disabled") is not None
