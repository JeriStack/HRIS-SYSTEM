"""
HRIS-SYSTEM — Employee Management Page Object
Models /hr/employees directory, filters, custom search/sort, and /hr/employees/create form.
"""

from typing import Tuple, List
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class EmployeeManagementPage(BasePage):
    """Page Object for HR Employee Directory and Creation."""

    PATH = "/hr/employees"
    CREATE_PATH = "/hr/employees/create"

    # Directory Locators
    PAGE_HEADER_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".content-header h1")
    ADD_EMPLOYEE_BTN: Tuple[By, str] = (By.XPATH, "//a[contains(@href, '/hr/employees/create')]")
    SEARCH_INPUT: Tuple[By, str] = (By.NAME, "q")
    DEPT_SELECT: Tuple[By, str] = (By.NAME, "dept")
    STATUS_SELECT: Tuple[By, str] = (By.NAME, "status")
    SEARCH_SUBMIT_BTN: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/hr/employees')]//button[contains(., 'Search')]")
    RESET_FILTERS_LINK: Tuple[By, str] = (By.XPATH, "//a[contains(., 'Reset') and contains(@href, '/hr/employees')]")
    EMPLOYEE_ROWS: Tuple[By, str] = (By.CSS_SELECTOR, "table.data-table tbody tr")
    TOTAL_COUNT_CARD: Tuple[By, str] = (By.XPATH, "//div[contains(., 'Total Workforce')]/following-sibling::*")

    # Create Form Locators
    EMP_NO_INPUT: Tuple[By, str] = (By.ID, "empNo")
    EMAIL_INPUT: Tuple[By, str] = (By.ID, "empEmail")
    FIRST_NAME_INPUT: Tuple[By, str] = (By.ID, "firstName")
    MIDDLE_NAME_INPUT: Tuple[By, str] = (By.ID, "middleName")
    LAST_NAME_INPUT: Tuple[By, str] = (By.ID, "lastName")
    PHONE_INPUT: Tuple[By, str] = (By.ID, "empPhone")
    BIRTH_DATE_INPUT: Tuple[By, str] = (By.ID, "birthDate")
    GENDER_SELECT: Tuple[By, str] = (By.ID, "empGender")
    ADDRESS_INPUT: Tuple[By, str] = (By.ID, "empAddress")
    FORM_DEPT_SELECT: Tuple[By, str] = (By.ID, "deptSelect")
    FORM_POS_SELECT: Tuple[By, str] = (By.ID, "posSelect")
    DATE_HIRED_INPUT: Tuple[By, str] = (By.ID, "dateHired")
    STATUS_SELECT_FORM: Tuple[By, str] = (By.ID, "empStatus")
    SUBMIT_BTN: Tuple[By, str] = (By.XPATH, "//form[@id='employeeForm']//button[@type='submit']")
    CANCEL_LINK: Tuple[By, str] = (By.XPATH, "//form[@id='employeeForm']//a[contains(@href, '/hr/employees')]")

    def open(self) -> "EmployeeManagementPage":
        super().open(self.PATH)
        return self

    def open_create(self) -> "EmployeeManagementPage":
        super().open(self.CREATE_PATH)
        return self

    def is_directory_loaded(self) -> bool:
        return self.is_visible(self.SEARCH_INPUT, timeout=5) and self.is_visible(self.ADD_EMPLOYEE_BTN, timeout=5)

    def is_create_form_loaded(self) -> bool:
        return self.is_visible(self.EMP_NO_INPUT, timeout=5) and self.is_visible(self.SUBMIT_BTN, timeout=5)

    def search_employee(self, query: str) -> "EmployeeManagementPage":
        self.type(self.SEARCH_INPUT, query)
        self.click(self.SEARCH_SUBMIT_BTN)
        return self

    def get_employee_row_count(self) -> int:
        return len(self.find_all(self.EMPLOYEE_ROWS))

    def fill_create_form(
        self,
        emp_no: str,
        email: str,
        first_name: str,
        last_name: str,
        birth_date: str = "1995-05-15",
        gender: str = "Male",
        phone: str = "+63 912 345 6789",
        date_hired: str = "2026-01-01",
    ) -> "EmployeeManagementPage":
        self.type(self.EMP_NO_INPUT, emp_no)
        self.type(self.EMAIL_INPUT, email)
        self.type(self.FIRST_NAME_INPUT, first_name)
        self.type(self.LAST_NAME_INPUT, last_name)
        self.type(self.PHONE_INPUT, phone)
        self.type(self.BIRTH_DATE_INPUT, birth_date)

        # Select gender
        gender_el = self.find_visible(self.GENDER_SELECT)
        for option in gender_el.find_elements(By.TAG_NAME, "option"):
            if option.get_attribute("value") == gender:
                option.click()
                break

        self.type(self.DATE_HIRED_INPUT, date_hired)
        return self

    def select_department_and_position(self, dept_name: str = None) -> "EmployeeManagementPage":
        dept_el = self.find_visible(self.FORM_DEPT_SELECT)
        options = dept_el.find_elements(By.TAG_NAME, "option")
        # Select first non-empty department
        for opt in options:
            val = opt.get_attribute("value")
            if val and (dept_name is None or dept_name.lower() in opt.text.lower()):
                opt.click()
                break

        # Select first non-empty position
        pos_el = self.find_visible(self.FORM_POS_SELECT)
        pos_options = pos_el.find_elements(By.TAG_NAME, "option")
        for opt in pos_options:
            val = opt.get_attribute("value")
            if val:
                opt.click()
                break
        return self

    def submit_form(self) -> None:
        self.click(self.SUBMIT_BTN)

