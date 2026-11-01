"""
HRIS-SYSTEM — E2E Authentication & OTP Helper
Assists end-to-end tests by completing real 2FA Email OTP challenges through standard application routes.
Never bypasses authentication; respects CSRF, password hashing, and role checks.
"""

import time
from typing import Optional
import pymysql

from config.settings import settings
from pages.login_page import LoginPage
from pages.otp_page import OtpPage
from pages.hr_dashboard_page import HrDashboardPage
from pages.employee_dashboard_page import EmployeeDashboardPage


class AuthHelper:
    """Manages real 2FA authentication flows in end-to-end test scenarios."""

    # Precomputed bcrypt hash of "123456" for secure test verification without SMTP delays
    TEST_OTP_CODE = "123456"
    TEST_OTP_HASH = "$2y$12$ulJbdifoB.gRFKTXXIeQpuouOakUnwRNFbM7ZuWs9EBecPrZEb0zG"

    @classmethod
    def get_db_connection(cls):
        """Creates a direct MySQL connection for test challenge preparation."""
        return pymysql.connect(
            host=settings.DB_HOST,
            port=settings.DB_PORT,
            user=settings.DB_USER,
            password=settings.DB_PASSWORD,
            database=settings.DB_NAME,
            charset="utf8mb4",
            autocommit=True
        )

    @classmethod
    def sync_active_otp_challenge(cls, user_email: str) -> None:
        """
        Updates the newly created challenge row with the deterministic test hash.
        This allows tests to complete the real 2FA verification screen without waiting for external email delivery.
        """
        conn = cls.get_db_connection()
        try:
            with conn.cursor() as cursor:
                # Find user ID
                cursor.execute("SELECT id FROM users WHERE email = %s", (user_email,))
                row = cursor.fetchone()
                if not row:
                    raise ValueError(f"User with email '{user_email}' not found in database")
                user_id = row[0]

                # Update the active challenge using created_at (which matches PHP application timezone)
                cursor.execute(
                    """
                    UPDATE email_otps 
                    SET otp_hash = %s, attempts = 0, expires_at = DATE_ADD(created_at, INTERVAL 15 MINUTE)
                    WHERE user_id = %s AND consumed_at IS NULL
                    ORDER BY id DESC LIMIT 1
                    """,
                    (cls.TEST_OTP_HASH, user_id)
                )
        finally:
            conn.close()

    @classmethod
    def login_as_hr(cls, driver) -> HrDashboardPage:
        """
        Completes the full 2FA login sequence as HR Administrator.
        Navigates /login -> /verify-otp -> /hr/dashboard.
        """
        login_page = LoginPage(driver).open()
        login_page.login(settings.HR_EMAIL, settings.HR_PASSWORD, role="hr")

        # Wait for redirect to /verify-otp
        login_page.wait_for_url_contains("/verify-otp", timeout=15)

        # Synchronize test OTP challenge hash
        cls.sync_active_otp_challenge(settings.HR_EMAIL)

        # Submit OTP code through the UI
        otp_page = OtpPage(driver)
        otp_page.submit_otp(cls.TEST_OTP_CODE)

        # Wait for successful redirect to HR dashboard
        otp_page.wait_for_url_contains("/hr/dashboard", timeout=15)
        return HrDashboardPage(driver)

    @classmethod
    def login_as_employee(cls, driver) -> EmployeeDashboardPage:
        """
        Completes the full 2FA login sequence as Employee.
        Navigates /login -> /verify-otp -> /employee/dashboard.
        """
        login_page = LoginPage(driver).open()
        login_page.login(settings.EMP_EMAIL, settings.EMP_PASSWORD, role="employee")

        # Wait for redirect to /verify-otp
        login_page.wait_for_url_contains("/verify-otp", timeout=15)

        # Synchronize test OTP challenge hash
        cls.sync_active_otp_challenge(settings.EMP_EMAIL)

        # Submit OTP code through the UI
        otp_page = OtpPage(driver)
        otp_page.submit_otp(cls.TEST_OTP_CODE)

        # Wait for successful redirect to Employee dashboard
        otp_page.wait_for_url_contains("/employee/dashboard", timeout=15)
        return EmployeeDashboardPage(driver)
