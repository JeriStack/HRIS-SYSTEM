"""
HRIS-SYSTEM — Department Management Page Object
Models /hr/departments directory, position alignment, and /hr/departments/create.
"""

from typing import Tuple, List
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class DepartmentPage(BasePage):
    """Page Object for HR Department Management."""

    PATH = "/hr/departments"
    CREATE_PATH = "/hr/departments/create"

    # Directory Locators
    PAGE_HEADER_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".content-header h1")
    ADD_DEPARTMENT_BTN: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/hr/departments/create')]")
    DEPARTMENT_ROWS: Tuple[By, str] = (By.CSS_SELECTOR, "table.data-table tbody tr")
    DEPARTMENT_NAMES: Tuple[By, str] = (By.CSS_SELECTOR, "table.data-table tbody tr strong")

    # Create Form Locators
    DEPT_NAME_INPUT: Tuple[By, str] = (By.ID, "deptName")
    DEPT_DESC_INPUT: Tuple[By, str] = (By.ID, "deptDesc")
    SUBMIT_BTN: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/hr/departments')]//button[@type='submit']")
    CANCEL_LINK: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/hr/departments')]//a[contains(@href, '/hr/departments')]")

    def open(self) -> "DepartmentPage":
        super().open(self.PATH)
        return self

    def open_create(self) -> "DepartmentPage":
        super().open(self.CREATE_PATH)
        return self

    def is_loaded(self) -> bool:
        return self.is_visible(self.ADD_DEPARTMENT_BTN, timeout=5) and "Departments" in self.get_text(self.PAGE_HEADER_TITLE)

    def is_create_form_loaded(self) -> bool:
        return self.is_visible(self.DEPT_NAME_INPUT, timeout=5) and self.is_visible(self.SUBMIT_BTN, timeout=5)

    def get_department_names(self) -> List[str]:
        elements = self.find_all(self.DEPARTMENT_NAMES)
        return [el.text.strip() for el in elements]

    def fill_form(self, name: str, description: str = "") -> "DepartmentPage":
        self.type(self.DEPT_NAME_INPUT, name)
        if description:
            self.type(self.DEPT_DESC_INPUT, description)
        return self

    def submit_form(self) -> None:
        self.click(self.SUBMIT_BTN)

