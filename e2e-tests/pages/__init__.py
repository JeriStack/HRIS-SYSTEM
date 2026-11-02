"""
Page Objects Package for HRIS-SYSTEM E2E UI Tests
"""

from pages.base_page import BasePage
from pages.login_page import LoginPage
from pages.otp_page import OtpPage
from pages.hr_dashboard_page import HrDashboardPage
from pages.employee_dashboard_page import EmployeeDashboardPage
from pages.employee_management_page import EmployeeManagementPage
from pages.department_page import DepartmentPage
from pages.leave_management_page import LeaveManagementPage

__all__ = [
    "BasePage",
    "LoginPage",
    "OtpPage",
    "HrDashboardPage",
    "EmployeeDashboardPage",
    "EmployeeManagementPage",
    "DepartmentPage",
    "LeaveManagementPage",
]
