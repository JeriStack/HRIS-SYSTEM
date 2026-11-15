"""
HRIS-SYSTEM — E2E Test Suite: Employee Dashboard
Tests Employee self-service widgets, leave balance cards, navigation, and sign-out.
"""

import time
import pytest
from selenium.webdriver.common.by import By
from config.settings import settings
from pages.base_page import BasePage
from pages.employee_dashboard_page import EmployeeDashboardPage
from pages.login_page import LoginPage


@pytest.mark.dashboard
@pytest.mark.smoke
class TestEmployeeDashboard:
    """Verifies self-service widgets and balance indicators on the Employee Dashboard."""

    def test_employee_dashboard_renders(self, authenticated_employee):
        """Validates Employee portal welcome banner, balance cards, and leave overview strip."""
        page = EmployeeDashboardPage(authenticated_employee)
        assert page.is_loaded(), "Employee Dashboard failed to render welcome banner or employee badge"
        assert "Welcome" in page.get_welcome_text()

        # Check balance cards
        balance_types = page.get_leave_balance_types()
        assert len(balance_types) >= 1, "Expected employee leave balance cards to be rendered"

        # Verify Executive Ledger overview strip on /employee/leave-balances
        balances_page = BasePage(authenticated_employee)
        balances_page.open("/employee/leave-balances")
        strip = balances_page.find_visible((By.CSS_SELECTOR, ".leave-overview-strip"), timeout=5)
        cells = authenticated_employee.find_elements(By.CSS_SELECTOR, ".leave-overview-cell")
        assert len(cells) == 4, "Expected 4 accounting cells in Paid Annual Leave Overview"

        for width, height in [(1440, 900), (1280, 800), (820, 900), (390, 844)]:
            authenticated_employee.set_window_size(width, height)
            metrics = authenticated_employee.execute_script(
                """
                const heads = Array.from(document.querySelectorAll('.leave-overview-cell-head'));
                const minGap = heads.reduce((acc, head) => {
                    const lbl = head.querySelector('.leave-overview-label');
                    const ico = head.querySelector('.leave-overview-icon');
                    if (!lbl || !ico) return acc;
                    return Math.min(acc, ico.getBoundingClientRect().left - lbl.getBoundingClientRect().right);
                }, 999);
                return {
                    minGap,
                    overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                    subWraps: Array.from(document.querySelectorAll('.leave-overview-sub')).filter(el => el.getClientRects().length > 1).length
                };
                """
            )
            assert metrics["minGap"] >= 4, f"Label-to-icon collision risk at {width}x{height}: {metrics}"
            assert metrics["overflowX"] <= 0, f"Horizontal overflow at {width}x{height}: {metrics}"
            if width >= 800:
                assert metrics["subWraps"] == 0, f"Subtext wrapped awkwardly at {width}x{height}: {metrics}"

        authenticated_employee.set_window_size(1280, 800)
        time.sleep(0.35)
        overview_section = authenticated_employee.find_element(By.CSS_SELECTOR, 'section[aria-labelledby="secOverviewHeading"]')
        theme = authenticated_employee.execute_script("return document.documentElement.getAttribute('data-theme') || 'light';")
        overview_section.screenshot(str(settings.SCREENSHOTS_DIR / f"leave_overview_{theme}_1280.png"))
        toggle_btns = authenticated_employee.find_elements(By.ID, "themeToggleBtn")
        if toggle_btns:
            toggle_btns[0].click()
            time.sleep(0.2)
            toggled_theme = authenticated_employee.execute_script("return document.documentElement.getAttribute('data-theme') || 'light';")
            overview_section.screenshot(str(settings.SCREENSHOTS_DIR / f"leave_overview_{toggled_theme}_1280.png"))
            assert strip.is_displayed()
            toggle_btns[0].click()
            time.sleep(0.2)

        authenticated_employee.set_window_size(390, 844)
        time.sleep(0.35)
        overview_section.screenshot(str(settings.SCREENSHOTS_DIR / "leave_overview_390.png"))

    def test_employee_navigation_to_file_leave(self, authenticated_employee):
        """Verifies clicking File Leave routes to /leaves/apply."""
        page = EmployeeDashboardPage(authenticated_employee)
        page.click_file_leave()
        page.wait_for_url_contains("/leaves/apply", timeout=5)
        assert "/leaves/apply" in authenticated_employee.current_url

    def test_employee_sign_out(self, authenticated_employee):
        """Verifies employee sign-out terminates the session and returns to /login."""
        page = EmployeeDashboardPage(authenticated_employee)
        page.click_sign_out()
        page.wait_for_url_contains("/login", timeout=5)
        assert "/login" in authenticated_employee.current_url
        assert LoginPage(authenticated_employee).is_login_page_loaded()

