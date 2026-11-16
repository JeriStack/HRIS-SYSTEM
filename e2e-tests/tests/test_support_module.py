"""
HRIS-SYSTEM — E2E Test Suite: Employee Help & Support and Complaint Management Module
Tests authenticated access controls, dashboard integration, support request & confidential complaint
submission, HR triage with internal-note isolation, status lifecycle closure, dark mode, and mobile layout.
"""

import re
import time
import uuid
import pytest
from selenium.common.exceptions import StaleElementReferenceException
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

from config.settings import settings
from pages.base_page import BasePage
from utils.auth_helper import AuthHelper


@pytest.mark.rbac
@pytest.mark.support
class TestSupportRbacAndAuth:
    """Verifies unauthenticated users cannot access Help & Support or HR Support routes."""

    @pytest.mark.parametrize("route", [
        "/support",
        "/support/request",
        "/support/complaint",
        "/hr/support",
        "/hr/support/handlers",
    ])
    def test_support_routes_require_authentication(self, driver, route):
        page = BasePage(driver)
        page.open(route)
        page.wait_for_url_contains("/login", timeout=8)
        assert "/login" in driver.current_url


@pytest.mark.support
class TestSupportModuleEndToEnd:
    """Full end-to-end verification across Employee self-service and HR triage."""

    @staticmethod
    def _set_viewport(driver, width: int, height: int) -> None:
        """Resize viewport cleanly with CDP override fallback for narrow widths."""
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
            const offenders = Array.from(document.querySelectorAll('*'))
                .filter(el => {
                    const st = getComputedStyle(el);
                    if (st.display === 'none' || st.visibility === 'hidden') return false;
                    const r = el.getBoundingClientRect();
                    return r.width > 0 && r.right > cw + 0.5;
                })
                .slice(0, 8)
                .map(el => ({
                    tag: el.tagName,
                    id: el.id || '',
                    cls: typeof el.className === 'string' ? el.className : '',
                    right: Math.round(el.getBoundingClientRect().right * 10) / 10,
                    cw
                }));
            return {
                scrollWidth: document.documentElement.scrollWidth,
                clientWidth: cw,
                overflowX: document.documentElement.scrollWidth - cw,
                offenders
            };
            """
        )
        assert metrics["overflowX"] <= 0, (
            f"Horizontal overflow on {label} at {width}x{height}: {metrics}"
        )

    @staticmethod
    def _capture_light_and_dark(driver, slug: str) -> None:
        settings.SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)
        first_theme = driver.execute_script("return document.documentElement.getAttribute('data-theme') || 'light';")
        driver.save_screenshot(str(settings.SCREENSHOTS_DIR / f"{slug}_{first_theme}_1440.png"))
        toggles = driver.find_elements(By.ID, "themeToggleBtn")
        if toggles:
            toggles[0].click()
            time.sleep(0.2)
            second_theme = driver.execute_script("return document.documentElement.getAttribute('data-theme') || 'light';")
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / f"{slug}_{second_theme}_1440.png"))
            toggles[0].click()
            time.sleep(0.15)

    @staticmethod
    def _scroll_click(driver, element) -> None:
        driver.execute_script("arguments[0].scrollIntoView({block: 'center', inline: 'nearest'});", element)
        time.sleep(0.15)
        try:
            element.click()
        except Exception:
            driver.execute_script("arguments[0].click();", element)

    @staticmethod
    def _wait_text_contains(driver, locator: tuple[str, str], text: str, timeout: int = 12) -> None:
        WebDriverWait(
            driver,
            timeout,
            ignored_exceptions=(StaleElementReferenceException,),
        ).until(
            lambda d: d.execute_script("return document.readyState === 'complete';")
            and text in d.find_element(*locator).text
        )

    @staticmethod
    def _submit_hr_response(driver, base, mode: str, body: str) -> None:
        """Post an HR response and wait for the redirected detail page to render it."""
        radio = base.find((By.CSS_SELECTOR, f'input[name="response_mode"][value="{mode}"]'))
        driver.execute_script("arguments[0].click();", radio)
        textarea = base.find((By.ID, "hrResponseBody"))
        textarea.clear()
        textarea.send_keys(body)
        submit_btn = base.find((By.ID, "hrResponseSubmitBtn"))
        TestSupportModuleEndToEnd._scroll_click(driver, submit_btn)
        # Wait for the POST -> redirect cycle to replace the old page before reading text.
        WebDriverWait(driver, 30).until(EC.staleness_of(textarea))
        TestSupportModuleEndToEnd._wait_text_contains(driver, (By.TAG_NAME, "body"), body, timeout=30)

    @staticmethod
    def _submit_hr_status(driver, base, status: str, remarks: str) -> None:
        """Change ticket status as HR and wait for the redirected page, surfacing any server-side error."""
        Select(base.find((By.ID, "hrStatusSelect"))).select_by_value(status)
        remarks_box = base.find((By.ID, "hrResolutionRemarks"))
        remarks_box.clear()
        remarks_box.send_keys(remarks)
        submit_btn = base.find((By.XPATH, '//form[contains(@action, "/status")]//button[@type="submit"]'))
        TestSupportModuleEndToEnd._scroll_click(driver, submit_btn)
        # Wait for the POST -> redirect cycle to replace the old page before reading it.
        WebDriverWait(driver, 30).until(EC.staleness_of(remarks_box))
        errors = driver.find_elements(By.CSS_SELECTOR, ".alert-danger")
        assert not errors, f"Status change to {status} was rejected: {errors[0].text}"
        TestSupportModuleEndToEnd._wait_text_contains(driver, (By.CSS_SELECTOR, ".page-title-group"), status, timeout=30)

    @staticmethod
    def _sign_out(driver) -> None:
        logout_btns = driver.find_elements(By.XPATH, "//form[contains(@action, '/logout')]//button")
        assert logout_btns, "Expected logout button in layout"
        driver.execute_script("arguments[0].click();", logout_btns[0])
        WebDriverWait(driver, 10).until(EC.url_contains("/login"))

    @staticmethod
    def _cleanup_e2e_tickets(ticket_ids: list[int]) -> None:
        ids = [int(tid) for tid in ticket_ids if int(tid) > 0]
        if not ids:
            return
        try:
            conn = AuthHelper.get_db_connection()
            try:
                placeholders = ",".join(["%s"] * len(ids))
                with conn.cursor() as cursor:
                    cursor.execute(f"DELETE FROM support_sensitive_access_logs WHERE ticket_id IN ({placeholders})", ids)
                    cursor.execute(
                        f"DELETE FROM notifications WHERE entity_id IN ({placeholders}) AND event_type LIKE %s",
                        [*ids, "support_%"],
                    )
                    cursor.execute(
                        f"DELETE FROM activity_logs WHERE entity_type = 'support_tickets' AND entity_id IN ({placeholders})",
                        [str(i) for i in ids],
                    )
                    cursor.execute(f"DELETE FROM support_tickets WHERE id IN ({placeholders})", ids)
            finally:
                conn.close()
        except Exception as exc:
            print(f"[test_support_module] Non-fatal cleanup note: {exc}")

    def test_employee_and_hr_support_workflow_light_dark_and_mobile(self, authenticated_employee):
        """Exercises Dashboard widget, Request & Complaint creation, HR internal note isolation, closure, and responsive layout."""
        driver = authenticated_employee
        base = BasePage(driver)
        tag = uuid.uuid4().hex[:8].upper()
        req_subject = f"E2E Support Request {tag}"
        comp_subject = f"E2E Workplace Complaint {tag}"
        internal_secret = f"INTERNAL_HR_SECRET_{tag}"
        public_reply = f"Public HR resolution update {tag}"
        created_ids: list[int] = []

        try:
            # 1. Verify Help & Support widget on Employee Dashboard
            self._set_viewport(driver, 1440, 900)
            req_links = driver.find_elements(By.CSS_SELECTOR, 'a[href*="/support/request"]')
            comp_links = driver.find_elements(By.CSS_SELECTOR, 'a[href*="/support/complaint"]')
            assert req_links, "Expected Submit Request action on Employee Dashboard"
            assert comp_links, "Expected Submit Complaint action on Employee Dashboard"

            # 2. Verify Support Center hub (/support) in Light, Dark, and Mobile 390x844
            base.open("/support")
            base.wait_for_url_contains("/support", timeout=8)
            assert "Help & Support" in driver.find_element(By.TAG_NAME, "body").text
            self._assert_zero_horizontal_overflow(driver, "Support Hub desktop", 1440, 900)
            self._capture_light_and_dark(driver, "support_hub")
            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "Support Hub mobile", 390, 844)
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / "support_hub_mobile_390.png"))

            # 3. Submit General Support Request with dynamic category fieldset toggle
            self._set_viewport(driver, 1440, 900)
            base.open("/support/request")
            cat_select = Select(base.find_visible((By.ID, "supportCategorySelect")))
            cat_select.select_by_value("Technical Issue")
            tech_panel = base.find((By.CSS_SELECTOR, '[data-category-panel="Technical Issue"]'))
            assert tech_panel.is_displayed(), "Technical Issue panel should appear when Technical Issue is selected"
            callout = base.find((By.ID, "complaintSwitchCallout"))
            assert not callout.is_displayed(), "Complaint callout must stay hidden unless Workplace Complaint is chosen"

            base.find((By.ID, "affectedModuleInput")).send_keys("Leave Balances CSV Export")
            base.find((By.ID, "supportSubjectInput")).send_keys(req_subject)
            base.find((By.ID, "supportDescriptionInput")).send_keys(
                "Automated E2E verification checking category-specific fields, ticket generation, and detail navigation."
            )
            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "Support Request Form mobile", 390, 844)
            self._set_viewport(driver, 1440, 900)

            submit_req_btn = base.find((By.XPATH, '//form[@data-support-form]//button[@type="submit"]'))
            self._scroll_click(driver, submit_req_btn)

            WebDriverWait(driver, 12).until(lambda d: bool(re.search(r"/support/\d+$", d.current_url)))
            req_match = re.search(r"/support/(\d+)$", driver.current_url)
            assert req_match
            req_ticket_id = int(req_match.group(1))
            created_ids.append(req_ticket_id)

            self._wait_text_contains(driver, (By.TAG_NAME, "body"), req_subject)
            page_text = driver.find_element(By.TAG_NAME, "body").text
            ref_match = re.search(r"SUP-\d{4}-\d{5}", page_text)
            assert ref_match, f"Expected SUP-YYYY-NNNNN ticket reference on detail page, got URL {driver.current_url}"
            req_ticket_ref = ref_match.group(0)
            assert req_subject in page_text

            # 4. Submit Confidential Workplace Complaint with non-anonymous acknowledgment
            base.open("/support/complaint")
            assert "not anonymous" in driver.find_element(By.TAG_NAME, "body").text.lower()
            Select(base.find_visible((By.ID, "complaintCategorySelect"))).select_by_value("Workplace Conduct")
            base.find((By.ID, "complaintSubjectInput")).send_keys(comp_subject)
            base.find((By.ID, "complaintDescriptionInput")).send_keys(
                "Confidential workplace complaint verification ensuring non-anonymous notice and isolated handling."
            )
            self._capture_light_and_dark(driver, "support_complaint")
            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "Complaint Form mobile", 390, 844)
            self._set_viewport(driver, 1440, 900)

            ack = base.find((By.ID, "complaintAckCheckbox"))
            if not ack.is_selected():
                self._scroll_click(driver, ack)

            self._scroll_click(
                driver,
                base.find((By.XPATH, '//form[contains(@action, "/support/complaint")]//button[@type="submit"]')),
            )
            WebDriverWait(driver, 12).until(lambda d: bool(re.search(r"/support/\d+$", d.current_url)))
            comp_match = re.search(r"/support/(\d+)$", driver.current_url)
            assert comp_match
            comp_ticket_id = int(comp_match.group(1))
            created_ids.append(comp_ticket_id)
            self._wait_text_contains(driver, (By.TAG_NAME, "body"), comp_subject)

            # 5. Sign out Employee -> Sign in as Primary Designated HR Director (hr01)
            self._sign_out(driver)
            AuthHelper.login_as_hr(driver)

            self._set_viewport(driver, 1440, 900)
            base.open("/hr/support")
            inbox_text = driver.find_element(By.TAG_NAME, "body").text
            assert req_ticket_ref in inbox_text, f"Expected {req_ticket_ref} in HR Support Inbox"
            assert comp_subject in inbox_text, "Expected designated HR (hr01) to see confidential complaint"
            self._capture_light_and_dark(driver, "hr_support_inbox")
            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "HR Support Inbox mobile", 390, 844)
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / "hr_support_inbox_mobile_390.png"))

            # 6. Triage Support Request as HR: Internal HR Note -> Public Reply -> Resolved
            self._set_viewport(driver, 1440, 900)
            base.open(f"/hr/support/{req_ticket_id}")

            self._submit_hr_response(driver, base, "internal_note", internal_secret)
            self._submit_hr_response(driver, base, "public_reply", public_reply)

            self._submit_hr_status(driver, base, "Resolved", f"Resolved export pagination quirk in Leave Balances ({tag}).")
            self._capture_light_and_dark(driver, "hr_support_detail")

            # Also confirm hr01 can open Confidential Complaint detail
            base.open(f"/hr/support/{comp_ticket_id}")
            complaint_detail_text = driver.find_element(By.TAG_NAME, "body").text
            assert "Confidential Case" in complaint_detail_text
            assert "Authorized Confidential Complaint Access Logged" in complaint_detail_text

            # 7. Sign back in as Employee: Verify Internal Note Isolation & Confirm Closure
            self._sign_out(driver)
            AuthHelper.login_as_employee(driver)

            self._set_viewport(driver, 1440, 900)
            base.open(f"/support/{req_ticket_id}")
            emp_detail_source = driver.page_source
            emp_detail_text = driver.find_element(By.TAG_NAME, "body").text

            assert public_reply in emp_detail_text, "Employee must see HR public response"
            assert internal_secret not in emp_detail_source, (
                "SECURITY VIOLATION: Private Internal HR Note leaked into employee view DOM!"
            )

            close_trigger = base.find((By.CSS_SELECTOR, '[data-dialog-open="confirmCloseDialog"]'))
            self._scroll_click(driver, close_trigger)
            base.find_visible((By.ID, "closeRemarksInput")).send_keys("Verified fix works cleanly.")
            self._scroll_click(driver, base.find((By.XPATH, '//dialog[@id="confirmCloseDialog"]//button[@type="submit"]')))
            WebDriverWait(
                driver,
                12,
                ignored_exceptions=(StaleElementReferenceException,),
            ).until(
                lambda d: d.execute_script("return document.readyState === 'complete';")
                and not d.find_elements(By.CSS_SELECTOR, "dialog[open]")
                and "Closed" in d.find_element(By.TAG_NAME, "body").text
            )

            self._set_viewport(driver, 390, 844)
            self._assert_zero_horizontal_overflow(driver, "Employee Ticket Detail mobile", 390, 844)
        finally:
            if hasattr(driver, "execute_cdp_cmd"):
                try:
                    driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
                except Exception:
                    pass
            self._cleanup_e2e_tickets(created_ids)
