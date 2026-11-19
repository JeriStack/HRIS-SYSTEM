"""
HRIS-SYSTEM — E2E Test Suite: Smart OTP Authentication & Trusted Device Management
Tests Mailpit Email OTP delivery, "Remember this device" opt-in, trusted-browser OTP bypass
across logout/login with cookie rotation, /account/security Light/Dark/Mobile rendering,
and self-service device revocation forcing fresh Email OTP on next login.
"""

import re
import time
import uuid
from typing import Any, Dict, List, Optional

import pytest
import requests
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import WebDriverWait

from config.settings import settings
from pages.base_page import BasePage
from pages.login_page import LoginPage
from utils.auth_helper import AuthHelper


def _mailpit_get_json(path: str, timeout: int = 15, attempts: int = 3) -> Dict[str, Any]:
    """Fetch JSON from Mailpit with retry resilience against transient Docker/WSL2 port-forward latency."""
    url = f"{settings.MAILPIT_API_URL}/{path.lstrip('/')}"
    last_exc: Optional[Exception] = None
    for attempt in range(attempts):
        try:
            resp = requests.get(url, timeout=timeout)
            resp.raise_for_status()
            return resp.json()
        except requests.RequestException as exc:
            last_exc = exc
            if attempt + 1 < attempts:
                time.sleep(1.0)
    raise last_exc  # type: ignore[misc]


def _fetch_mailpit_messages_for(email: str) -> List[Dict[str, Any]]:
    """Return Mailpit message summaries addressed to `email`, newest first."""
    payload = _mailpit_get_json("messages")
    messages = payload.get("messages") or []
    target = email.lower()
    matched = []
    for msg in messages:
        recipients = [
            (r.get("Address") or "").lower()
            for r in (msg.get("To") or [])
        ]
        if target in recipients:
            matched.append(msg)
    return matched


def _fetch_mailpit_otp_messages_for(email: str) -> List[Dict[str, Any]]:
    """Return Mailpit OTP emails (excluding security alerts) addressed to `email`."""
    return [
        msg
        for msg in _fetch_mailpit_messages_for(email)
        if "Your Sign-In Verification Code" in str(msg.get("Subject") or "")
    ]


def _wait_for_new_otp_code(
    email: str,
    known_message_ids: set,
    timeout: int = 15,
) -> tuple[str, str]:
    """Poll Mailpit for a newly delivered 6-digit OTP email for `email`."""
    deadline = time.time() + timeout
    while time.time() < deadline:
        for msg in _fetch_mailpit_otp_messages_for(email):
            msg_id = str(msg.get("ID") or "")
            if not msg_id or msg_id in known_message_ids:
                continue
            detail = _mailpit_get_json(f"message/{msg_id}")
            text_body = detail.get("Text") or ""
            html_body = detail.get("HTML") or ""
            combined = f"{text_body}\n{html_body}"
            # Strip HTML tags before scanning for standalone 6-digit OTP code
            plain = re.sub(r"<[^>]+>", " ", combined)
            match = re.search(r"\b(\d{6})\b", plain)
            if match:
                return match.group(1), msg_id
        time.sleep(0.4)
    raise TimeoutError(f"Timed out after {timeout}s waiting for OTP email to {email} in Mailpit.")


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
    time.sleep(0.2)


def _assert_zero_horizontal_overflow(driver, label: str, width: int, height: int) -> None:
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


def _sign_out(driver) -> None:
    logout_btns = driver.find_elements(By.XPATH, "//form[contains(@action, '/logout')]//button")
    assert logout_btns, "Expected sign-out button in workspace layout."
    driver.execute_script("arguments[0].click();", logout_btns[0])
    WebDriverWait(driver, 10).until(EC.url_contains("/login"))


def _create_temp_employee_user() -> Optional[Dict[str, Any]]:
    """Create an isolated temporary active Employee account in hris_db for Smart OTP E2E tests."""
    suffix = uuid.uuid4().hex[:8]
    email = f"smart.otp.{suffix}@company.test"
    username = f"smart_otp_{suffix}"
    conn = AuthHelper.get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                "SELECT password FROM users WHERE email = %s LIMIT 1",
                (settings.EMP_EMAIL,),
            )
            seed_row = cursor.fetchone()
            if not seed_row:
                return None
            password_hash = seed_row[0] if isinstance(seed_row, tuple) else seed_row["password"]
            cursor.execute(
                """
                INSERT INTO users (username, email, password, role, status, created_at)
                VALUES (%s, %s, %s, 'employee', 'Active', NOW())
                """,
                (username, email, password_hash),
            )
            user_id = cursor.lastrowid
        conn.commit()
        return {
            "id": int(user_id),
            "email": email,
            "username": username,
            "password": settings.EMP_PASSWORD,
        }
    finally:
        conn.close()


def _cleanup_temp_user(user_id: int, mailpit_ids: List[str]) -> None:
    if user_id > 0:
        try:
            conn = AuthHelper.get_db_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("DELETE FROM trusted_devices WHERE user_id = %s", (user_id,))
                    cursor.execute("DELETE FROM user_auth_security_states WHERE user_id = %s", (user_id,))
                    cursor.execute("DELETE FROM email_otps WHERE user_id = %s", (user_id,))
                    cursor.execute("DELETE FROM notifications WHERE user_id = %s", (user_id,))
                    cursor.execute("DELETE FROM activity_logs WHERE user_id = %s", (user_id,))
                    cursor.execute("DELETE FROM users WHERE id = %s", (user_id,))
                conn.commit()
            finally:
                conn.close()
        except Exception as exc:
            print(f"[test_smart_otp_trusted_devices] DB cleanup note: {exc}")

    if mailpit_ids:
        try:
            requests.delete(
                f"{settings.MAILPIT_API_URL}/messages",
                json={"ids": mailpit_ids},
                timeout=5,
            )
        except Exception as exc:
            print(f"[test_smart_otp_trusted_devices] Mailpit cleanup note: {exc}")


@pytest.mark.rbac
@pytest.mark.auth
class TestSmartOtpSecurityAccessControl:
    """Verifies unauthenticated access to Smart OTP self-service security routes fails closed."""

    @pytest.mark.parametrize("route", [
        "/account/security",
    ])
    def test_account_security_requires_authentication(self, driver, route):
        page = BasePage(driver)
        page.open(route)
        page.wait_for_url_contains("/login", timeout=8)
        assert "/login" in driver.current_url


@pytest.mark.auth
class TestSmartOtpTrustedDeviceLifecycle:
    """End-to-end verification of Smart OTP bypass, cookie security, UI, and revocation."""

    def test_smart_otp_trust_bypass_ui_and_revocation(self, driver):
        temp_user = _create_temp_employee_user()
        if temp_user is None:
            pytest.skip(f"Seeded reference account {settings.EMP_EMAIL} missing in hris_db.")

        temp_user_id = int(temp_user["id"])
        temp_email = str(temp_user["email"])
        temp_password = str(temp_user["password"])
        seen_mail_ids: set[str] = set()
        created_mail_ids: List[str] = []

        try:
            # 1. First login from untrusted browser requires Email OTP
            login_page = LoginPage(driver).open()
            login_page.login(temp_email, temp_password, role="employee")
            WebDriverWait(driver, 10).until(EC.url_contains("/verify-otp"))
            assert "/verify-otp" in driver.current_url
            assert driver.get_cookie("nexora_td") is None, (
                "Untrusted browser must not hold a trusted-device cookie before OTP verification."
            )

            otp_code_1, mail_id_1 = _wait_for_new_otp_code(temp_email, seen_mail_ids)
            seen_mail_ids.add(mail_id_1)
            created_mail_ids.append(mail_id_1)

            # 2. Remember-this-device checkbox must default unchecked; select it BEFORE 6th OTP digit
            remember_cb = WebDriverWait(driver, 8).until(
                EC.visibility_of_element_located((By.ID, "rememberDeviceCheckbox"))
            )
            assert remember_cb.is_displayed()
            assert not remember_cb.is_selected(), "Remember-this-device must default to unchecked."
            driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", remember_cb)
            remember_cb.click()
            assert remember_cb.is_selected()

            for idx, digit in enumerate(otp_code_1):
                box = driver.find_element(By.ID, f"otp_digit_{idx}")
                box.send_keys(digit)

            try:
                WebDriverWait(driver, 5).until(EC.url_contains("/employee/dashboard"))
            except TimeoutException:
                submit_btn = driver.find_element(By.ID, "verifySubmitBtn")
                driver.execute_script("arguments[0].click();", submit_btn)
                WebDriverWait(driver, 10).until(EC.url_contains("/employee/dashboard"))

            # Verify HttpOnly, SameSite=Lax trusted-device cookie issued after OTP verification
            td_cookie_1 = driver.get_cookie("nexora_td")
            assert td_cookie_1 is not None, "Expected nexora_td cookie after trusting browser."
            assert td_cookie_1.get("httpOnly") is True, f"nexora_td must be HttpOnly: {td_cookie_1}"
            assert td_cookie_1.get("sameSite", "").lower() == "lax"
            assert "." in str(td_cookie_1.get("value") or "")
            initial_cookie_value = str(td_cookie_1["value"])

            # Verify trust confirmation banner & navigation link to /account/security
            security_nav = WebDriverWait(driver, 8).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, "a[href$='/account/security']"))
            )
            driver.execute_script("arguments[0].click();", security_nav)
            WebDriverWait(driver, 10).until(EC.url_contains("/account/security"))
            assert "Remembered Browser" in driver.page_source
            assert "This Browser" in driver.page_source

            # 3. Ordinary logout preserves nexora_td; second login skips Email OTP completely
            _sign_out(driver)
            preserved_cookie = driver.get_cookie("nexora_td")
            assert preserved_cookie is not None and preserved_cookie.get("value") == initial_cookie_value, (
                "Standard sign-out must preserve trusted-device cookie so browser remains remembered."
            )

            login_page = LoginPage(driver).open()
            login_page.login(temp_email, temp_password, role="employee")
            WebDriverWait(driver, 10).until(EC.url_contains("/employee/dashboard"))
            assert "/verify-otp" not in driver.current_url

            # Ensure NO extra OTP email was sent on trusted login and validator rotated
            time.sleep(0.8)
            assert len(_fetch_mailpit_otp_messages_for(temp_email)) == 1, (
                "Trusted-device login must NOT send an unnecessary Email OTP."
            )
            td_cookie_2 = driver.get_cookie("nexora_td")
            assert td_cookie_2 is not None
            assert td_cookie_2.get("value") != initial_cookie_value, (
                "Trusted-device validator must rotate atomically upon trusted login."
            )

            # 4. Inspect /account/security layout across Desktop, Dark Mode, and Mobile (390x844)
            driver.get(f"{settings.BASE_URL}/account/security")
            WebDriverWait(driver, 10).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, ".security-this-browser-pill"))
            )
            assert "Password + Trusted Device" in driver.page_source

            _set_viewport(driver, 1440, 900)
            _assert_zero_horizontal_overflow(driver, "account-security-desktop", 1440, 900)

            settings.SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)
            theme_before = driver.execute_script(
                "return document.documentElement.getAttribute('data-theme') || 'light';"
            )
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / f"account_security_{theme_before}_1440.png"))
            toggle_btns = driver.find_elements(By.ID, "themeToggleBtn")
            if toggle_btns:
                driver.execute_script("arguments[0].click();", toggle_btns[0])
                time.sleep(0.2)
                theme_after = driver.execute_script(
                    "return document.documentElement.getAttribute('data-theme') || 'light';"
                )
                driver.save_screenshot(str(settings.SCREENSHOTS_DIR / f"account_security_{theme_after}_1440.png"))
                driver.execute_script("arguments[0].click();", toggle_btns[0])
                time.sleep(0.15)

            _set_viewport(driver, 390, 844)
            _assert_zero_horizontal_overflow(driver, "account-security-mobile", 390, 844)
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / "account_security_mobile_390.png"))

            _set_viewport(driver, 1440, 900)
            if hasattr(driver, "execute_cdp_cmd"):
                try:
                    driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
                except Exception:
                    pass

            # 5. Revoke current trusted browser via native <dialog> confirmation
            revoke_btn = WebDriverWait(driver, 8).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, "button[data-revoke-device][data-is-current='1']"))
            )
            driver.execute_script("arguments[0].scrollIntoView({block: 'center'});", revoke_btn)
            driver.execute_script("arguments[0].click();", revoke_btn)

            dialog = WebDriverWait(driver, 6).until(
                EC.presence_of_element_located((By.CSS_SELECTOR, "dialog#revokeDeviceDialog[open]"))
            )
            assert dialog.is_displayed()
            current_note = driver.find_element(By.ID, "revokeDeviceCurrentWarning")
            assert current_note.is_displayed()

            confirm_btn = driver.find_element(By.CSS_SELECTOR, "#revokeDeviceDialogForm button[type='submit']")
            confirm_btn.click()

            WebDriverWait(driver, 10).until(
                lambda d: "/account/security" in d.current_url and "OTP Required Next Login" in d.page_source
            )
            assert driver.get_cookie("nexora_td") is None, (
                "Revoking the active browser must clear the nexora_td cookie."
            )

            # 6. Next login requires Email OTP again and dispatches a new OTP via Mailpit
            _sign_out(driver)
            login_page = LoginPage(driver).open()
            login_page.login(temp_email, temp_password, role="employee")
            WebDriverWait(driver, 10).until(EC.url_contains("/verify-otp"))

            otp_code_2, mail_id_2 = _wait_for_new_otp_code(temp_email, seen_mail_ids)
            created_mail_ids.append(mail_id_2)
            assert len(otp_code_2) == 6
            assert mail_id_2 != mail_id_1
        finally:
            if hasattr(driver, "execute_cdp_cmd"):
                try:
                    driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
                except Exception:
                    pass
            try:
                all_ids = [
                    str(m.get("ID"))
                    for m in _fetch_mailpit_messages_for(temp_email)
                    if m.get("ID")
                ]
                for mid in created_mail_ids:
                    if mid not in all_ids:
                        all_ids.append(mid)
            except Exception:
                all_ids = created_mail_ids
            _cleanup_temp_user(temp_user_id, all_ids)
