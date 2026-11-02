"""
HRIS-SYSTEM — HR Dashboard Page Object
Models /hr/dashboard, operational metric cards, navigation sidebar, and quick actions.
"""

from typing import Tuple, List
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class HrDashboardPage(BasePage):
    """Page Object for the HR Operations Portal."""

    PATH = "/hr/dashboard"

    # Locators
    PAGE_HEADER_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".page-title-group h1")
    ROLE_BADGE: Tuple[By, str] = (By.CSS_SELECTOR, ".role-badge.hr")
    STAT_CARDS: Tuple[By, str] = (By.CSS_SELECTOR, ".stat-card")
    STAT_VALUES: Tuple[By, str] = (By.CSS_SELECTOR, ".stat-value")
    ADD_EMPLOYEE_BTN: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/hr/employees/create')]")
    SIDEBAR_EMPLOYEES_LINK: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/hr/employees')]")
    SIDEBAR_DEPARTMENTS_LINK: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/hr/departments')]")
    SIDEBAR_LEAVES_LINK: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/leaves') and not(contains(@href, '/balances'))]")
    LOGOUT_BUTTON: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/logout')]//button")

    def open(self) -> "HrDashboardPage":
        super().open(self.PATH)
        return self

    def is_loaded(self) -> bool:
        return self.is_visible(self.ROLE_BADGE, timeout=5) and "Human Resources" in self.get_text(self.PAGE_HEADER_TITLE)

    def get_header_text(self) -> str:
        return self.get_text(self.PAGE_HEADER_TITLE)

    def get_stat_values(self) -> List[str]:
        elements = self.find_all(self.STAT_VALUES)
        return [el.text.strip() for el in elements]

    def click_add_employee(self) -> None:
        self.click(self.ADD_EMPLOYEE_BTN)

    def click_employees_directory(self) -> None:
        self.click(self.SIDEBAR_EMPLOYEES_LINK)

    def click_departments(self) -> None:
        self.click(self.SIDEBAR_DEPARTMENTS_LINK)

    def click_leave_queue(self) -> None:
        self.click(self.SIDEBAR_LEAVES_LINK)

    def click_sign_out(self) -> None:
        self.click(self.LOGOUT_BUTTON)

