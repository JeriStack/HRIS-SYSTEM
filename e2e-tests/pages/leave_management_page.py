"""
HRIS-SYSTEM — Leave Management Page Object
Models /leaves/apply (employee filing), /leaves (HR review queue & FIFO), and balance indicators.
"""

from typing import Tuple, List
from selenium.webdriver.common.by import By
from pages.base_page import BasePage


class LeaveManagementPage(BasePage):
    """Page Object for Leave Application and Approval Operations."""

    APPLY_PATH = "/leaves/apply"
    REVIEW_PATH = "/leaves"
    MY_REQUESTS_PATH = "/employee/leave-requests"

    # Employee Filing Form Locators
    LEAVE_TYPE_SELECT: Tuple[By, str] = (By.ID, "leaveTypeSelect")
    START_DATE_INPUT: Tuple[By, str] = (By.ID, "leaveStartDate")
    END_DATE_INPUT: Tuple[By, str] = (By.ID, "leaveEndDate")
    REASON_TEXTAREA: Tuple[By, str] = (By.ID, "leaveReason")
    CALCULATED_DAYS_DISPLAY: Tuple[By, str] = (By.ID, "calculatedDaysDisplay")
    APPLY_SUBMIT_BTN: Tuple[By, str] = (By.XPATH, "//form[@id='leaveApplicationForm']//button[@type='submit']")
    APPLY_PAGE_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".page-title-group h1")

    # HR Review Queue Locators
    QUEUE_HEADER_TITLE: Tuple[By, str] = (By.CSS_SELECTOR, ".page-title-group h1")
    PROCESS_NEXT_FIFO_BTN: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/leaves/queue/next')]//button")
    QUEUE_ROWS: Tuple[By, str] = (By.CSS_SELECTOR, "table.table tbody tr")
    STATUS_FILTER_PENDING: Tuple[By, str] = (By.XPATH, "//a[contains(@href, 'status=Pending')]")
    STATUS_FILTER_ALL: Tuple[By, str] = (By.XPATH, "//a[contains(., 'All Requests')]")
    APPROVE_BUTTONS: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/approve')]//button")
    REJECT_BUTTONS: Tuple[By, str] = (By.XPATH, "//form[contains(@action, '/reject')]//button")

    def open_apply(self) -> "LeaveManagementPage":
        super().open(self.APPLY_PATH)
        return self

    def open_review_queue(self) -> "LeaveManagementPage":
        super().open(self.REVIEW_PATH)
        return self

    def is_apply_loaded(self) -> bool:
        return self.is_visible(self.LEAVE_TYPE_SELECT, timeout=5) and self.is_visible(self.APPLY_SUBMIT_BTN, timeout=5)

    def is_review_queue_loaded(self) -> bool:
        return self.is_visible(self.QUEUE_HEADER_TITLE, timeout=5) and "Leave Review" in self.get_text(self.QUEUE_HEADER_TITLE)

    def select_first_available_leave_type(self) -> "LeaveManagementPage":
        select_el = self.find_visible(self.LEAVE_TYPE_SELECT)
        options = select_el.find_elements(By.TAG_NAME, "option")
        for opt in options:
            val = opt.get_attribute("value")
            if val and val != "":
                opt.click()
                break
        return self

    def fill_leave_form(self, start_date: str, end_date: str, reason: str) -> "LeaveManagementPage":
        self.select_first_available_leave_type()
        self.type(self.START_DATE_INPUT, start_date)
        self.type(self.END_DATE_INPUT, end_date)
        self.type(self.REASON_TEXTAREA, reason)
        return self

    def submit_application(self) -> None:
        self.click(self.APPLY_SUBMIT_BTN)

    def get_queue_rows_count(self) -> int:
        return len(self.find_all(self.QUEUE_ROWS))

