"""
HRIS-SYSTEM — Employee Dashboard Page Object
Models /employee/dashboard, balance cards, personal information, and quick leave filing.
"""

from typing import Tuple, List
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class EmployeeDashboardPage(BasePage):
    """Page Object for the Employee Self-Service Dashboard."""

    PATH = "/employee/dashboard"

    # Locators
    PAGE_HEADER_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".page-title-group h1")
    ROLE_BADGE: Tuple[By, str] = (By.CSS_SELECTOR, ".role-badge.emp")
    VIEW_PROFILE_BTN: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/employee/profile')]")
    FILE_LEAVE_BTN: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/leaves/apply')]")
    BALANCE_CARDS: Tuple[By, str] = (By.CSS_SELECTOR, ".balance-card")
    BALANCE_TITLES: Tuple[By, str] = (By.CSS_SELECTOR, ".balance-title strong")
    LOGOUT_BUTTON: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/logout')]//button")

    def open(self) -> "EmployeeDashboardPage":
        super().open(self.PATH)
        return self

    def is_loaded(self) -> bool:
        return self.is_visible(self.ROLE_BADGE, timeout=5) and "Welcome" in self.get_text(self.PAGE_HEADER_TITLE)

    def get_welcome_text(self) -> str:
        return self.get_text(self.PAGE_HEADER_TITLE)

    def click_file_leave(self) -> None:
        self.click(self.FILE_LEAVE_BTN)

    def click_view_profile(self) -> None:
        self.click(self.VIEW_PROFILE_BTN)

    def get_leave_balance_types(self) -> List[str]:
        elements = self.find_all(self.BALANCE_TITLES)
        return [el.text.strip() for el in elements]

    def click_sign_out(self) -> None:
        self.click(self.LOGOUT_BUTTON)

