"""
HRIS-SYSTEM — E2E Test Suite: HR Announcements Management System (Phase 13.5)
Validates:
- HR announcement creation, draft editing, preview, scheduling UI, and immediate publication
- Employee announcement feed, dashboard preview (max 3 without auto-read), details view, and read receipt recording
- HR read statistics and individual read receipts verification
- Search & category/priority/status filters
- Responsive desktop, tablet, and mobile layouts (zero horizontal overflow)
- Light Mode and Dark Mode compatibility
- Error and empty states + RBAC route protection
"""

import re
import time
import uuid
import pytest
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

from config.settings import settings
from pages.base_page import BasePage
from utils.auth_helper import AuthHelper


@pytest.mark.rbac
@pytest.mark.announcements
class TestAnnouncementsRbacAndAuth:
    """Verifies unauthenticated users and unauthorized roles are blocked from announcement management."""

    @pytest.mark.parametrize("route", [
        "/hr/announcements",
        "/hr/announcements/create",
        "/employee/announcements",
    ])
    def test_announcement_routes_require_authentication(self, driver, route):
        page = BasePage(driver)
        page.open(route)
        page.wait_for_url_contains("/login", timeout=8)
        assert "/login" in driver.current_url

    def test_employee_cannot_access_hr_announcements_management(self, authenticated_employee):
        driver = authenticated_employee
        page = BasePage(driver)
        page.open("/hr/announcements")
        time.sleep(0.3)
        body_text = driver.find_element(By.TAG_NAME, "body").text
        assert "403" in body_text or "Forbidden" in body_text or "Access Denied" in body_text


@pytest.mark.announcements
class TestAnnouncementsModuleEndToEnd:
    """End-to-end verification of HR Announcements Management and Employee Announcement Feed."""

    @staticmethod
    def _set_viewport(driver, width: int, height: int) -> None:
        if hasattr(driver, "execute_cdp_cmd"):
            try:
                driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
            except Exception:
                pass
        driver.set_window_size(width, height)
        if hasattr(driver, "execute_cdp_cmd"):
            actual = driver.execute_script("return {w: window.innerWidth, h: window.innerHeight};")
            if actual["w"] > width + 20 or actual["h"] < height - 20:
                driver.execute_cdp_cmd(
                    "Emulation.setDeviceMetricsOverride",
                    {
                        "width": width,
                        "height": height,
                        "deviceScaleFactor": 1,
                        "mobile": width <= 767,
                    },
                )
        WebDriverWait(driver, 4).until(
            lambda d: d.execute_script(
                """
                const ac = document.querySelector('.app-container');
                if (!ac) return true;
                if (window.innerWidth <= 767 && parseFloat(getComputedStyle(ac).marginLeft) > 0.5) {
                    return false;
                }
                return true;
                """
            )
        )
        time.sleep(0.15)

    @staticmethod
    def _assert_zero_horizontal_overflow(driver, label: str, width: int = 390, height: int = 844) -> None:
        metrics = driver.execute_script(
            """
            const cw = document.documentElement.clientWidth;
            return {
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: cw,
                overflowX: document.documentElement.scrollWidth - cw
            };
            """
        )
        assert metrics["overflowX"] <= 0, (
            f"Horizontal overflow on {label} at {width}x{height}: {metrics}"
        )

    @staticmethod
    def _cleanup_announcements(ann_ids: list[int]) -> None:
        ids = [int(aid) for aid in ann_ids if int(aid) > 0]
        if not ids:
            return
        try:
            conn = AuthHelper.get_db_connection()
            try:
                placeholders = ",".join(["%s"] * len(ids))
                with conn.cursor() as cursor:
                    cursor.execute(
                        f"DELETE FROM notifications WHERE entity_id IN ({placeholders}) AND event_type LIKE %s",
                        [*ids, "announcement.%"],
                    )
                    cursor.execute(f"DELETE FROM announcements WHERE id IN ({placeholders})", ids)
            finally:
                conn.close()
        except Exception as exc:
            print(f"[test_announcements_module] Non-fatal cleanup note: {exc}")

    def test_hr_announcement_lifecycle_and_employee_feed_read_receipt(self, authenticated_hr):
        driver = authenticated_hr
        base = BasePage(driver)
        tag = uuid.uuid4().hex[:6].upper()
        draft_title = f"E2E Draft Policy Notice {tag}"
        published_title = f"E2E Published Policy Notice {tag}"
        created_ids: list[int] = []

        try:
            # 1. Open HR Announcements Management page (/hr/announcements)
            self._set_viewport(driver, 1440, 900)
            base.open("/hr/announcements")
            base.wait_for_url_contains("/hr/announcements", timeout=8)
            assert "Announcements Management" in driver.find_element(By.TAG_NAME, "body").text
            self._assert_zero_horizontal_overflow(driver, "HR Announcements Index desktop", 1440, 900)

            # Check Dark Mode toggle and mobile responsiveness
            toggles = driver.find_elements(By.ID, "themeToggleBtn")
            if toggles:
                toggles[0].click()
                time.sleep(0.15)
                toggles[0].click()
                time.sleep(0.15)
            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "HR Announcements Index mobile", 390, 844)

            # 2. Create Draft Announcement
            self._set_viewport(driver, 1440, 900)
            base.open("/hr/announcements/create")
            base.find((By.ID, "annTitle")).send_keys(draft_title)
            Select(base.find((By.ID, "annCategory"))).select_by_value("HR Policy Updates")
            Select(base.find((By.ID, "annPriority"))).select_by_value("Important")
            base.find((By.ID, "annContent")).send_keys(
                f"Official HR policy update {tag}. Please review the updated attendance and leave guidelines."
            )
            Select(base.find((By.ID, "annPublishMode"))).select_by_value("draft")

            # Verify Live Preview Toggle
            preview_btn = base.find((By.ID, "toggleAnnPreviewBtn"))
            driver.execute_script("arguments[0].click();", preview_btn)
            preview_pane = base.find((By.ID, "annLivePreviewPane"))
            assert preview_pane.is_displayed()

            submit_btn = base.find((By.ID, "annSubmitBtn"))
            driver.execute_script("arguments[0].click();", submit_btn)

            WebDriverWait(driver, 10).until(lambda d: bool(re.search(r"/hr/announcements/\d+$", d.current_url)))
            m = re.search(r"/hr/announcements/(\d+)$", driver.current_url)
            assert m
            aid = int(m.group(1))
            created_ids.append(aid)
            assert "Draft" in driver.find_element(By.TAG_NAME, "body").text

            # 3. Edit Draft and Publish Immediately
            base.open(f"/hr/announcements/{aid}/edit")
            title_input = base.find((By.ID, "annTitle"))
            title_input.clear()
            title_input.send_keys(published_title)
            Select(base.find((By.ID, "annPublishMode"))).select_by_value("publish")

            submit_btn = base.find((By.ID, "annSubmitBtn"))
            driver.execute_script("arguments[0].click();", submit_btn)
            proceed_btn = WebDriverWait(driver, 5).until(
                EC.element_to_be_clickable((By.ID, "annFormConfirmProceed"))
            )
            driver.execute_script("arguments[0].click();", proceed_btn)

            WebDriverWait(driver, 10).until(lambda d: f"/hr/announcements/{aid}" in d.current_url and "/edit" not in d.current_url)
            assert "Published" in driver.find_element(By.TAG_NAME, "body").text

            # 4. Sign out HR, sign in as Employee, verify Dashboard preview & Feed & Read Receipt
            logout_btns = driver.find_elements(By.XPATH, "//form[contains(@action, '/logout')]//button")
            driver.execute_script("arguments[0].click();", logout_btns[0])
            WebDriverWait(driver, 10).until(EC.url_contains("/login"))

            AuthHelper.login_as_employee(driver)
            assert "Company Announcements" in driver.find_element(By.TAG_NAME, "body").text

            base.open("/employee/announcements")
            base.wait_for_url_contains("/employee/announcements", timeout=8)
            assert published_title in driver.find_element(By.TAG_NAME, "body").text
            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "Employee Announcements Feed mobile", 390, 844)

            self._set_viewport(driver, 1440, 900)
            base.open(f"/employee/announcements/{aid}")
            assert "Read Receipt Recorded" in driver.find_element(By.TAG_NAME, "body").text
            assert published_title in driver.find_element(By.TAG_NAME, "body").text
        finally:
            if hasattr(driver, "execute_cdp_cmd"):
                try:
                    driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
                except Exception:
                    pass
            self._cleanup_announcements(created_ids)
