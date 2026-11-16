"""
HRIS-SYSTEM — E2E Test Suite: Public Help & Support, Password Recovery, and Login Assistance
Exercises unauthenticated Help & Support entry points, Light/Dark & 390x844 mobile rendering,
Forgot Password -> Mailpit reset email -> Reset Password -> Trusted Device/Session revocation ->
mandatory Email OTP step-up, and Public AUTH-YYYY-NNNNN ticket submission -> Mailpit tracking URL ->
HR inbox triage with strict internal-note isolation and requester follow-up reply.
"""

import base64
import re
import shutil
import tempfile
import time
import uuid
from pathlib import Path
from typing import Any, Dict, List, Optional

import pytest
import requests
from selenium.common.exceptions import StaleElementReferenceException, TimeoutException, WebDriverException
from selenium.webdriver.common.by import By
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import Select, WebDriverWait

from config.settings import settings
from pages.base_page import BasePage
from pages.login_page import LoginPage
from utils.auth_helper import AuthHelper


MINIMAL_PNG_BYTES = base64.b64decode(
    "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=="
)


def _fetch_mailpit_messages_for(email: str) -> List[Dict[str, Any]]:
    resp = requests.get(f"{settings.MAILPIT_API_URL}/messages", timeout=5)
    resp.raise_for_status()
    payload = resp.json()
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


def _wait_for_mail_matching(
    email: str,
    subject_substring: str,
    known_ids: set[str],
    timeout: int = 15,
) -> tuple[Dict[str, Any], str]:
    deadline = time.time() + timeout
    needle = subject_substring.lower()
    while time.time() < deadline:
        for msg in _fetch_mailpit_messages_for(email):
            msg_id = str(msg.get("ID") or "")
            if not msg_id or msg_id in known_ids:
                continue
            subject = str(msg.get("Subject") or "")
            if needle not in subject.lower():
                continue
            detail_resp = requests.get(
                f"{settings.MAILPIT_API_URL}/message/{msg_id}",
                timeout=5,
            )
            detail_resp.raise_for_status()
            return detail_resp.json(), msg_id
        time.sleep(0.4)
    raise TimeoutError(
        f"Timed out after {timeout}s waiting for Mailpit email matching '{subject_substring}' to {email}."
    )


def _extract_token_url(detail: Dict[str, Any], route_path: str) -> str:
    combined = f"{detail.get('Text') or ''}\n{detail.get('HTML') or ''}"
    combined = combined.replace("&amp;", "&")
    pattern = rf"https?://[^\s\"'<>]+{re.escape(route_path)}\?token=([a-f0-9]+\.[a-f0-9]+)"
    match = re.search(pattern, combined)
    assert match, f"Expected link containing {route_path}?token=... in email body"
    token = match.group(1)
    return f"{settings.BASE_URL}{route_path}?token={token}"


def _extract_otp_code(detail: Dict[str, Any]) -> str:
    combined = f"{detail.get('Text') or ''}\n{detail.get('HTML') or ''}"
    plain = re.sub(r"<[^>]+>", " ", combined)
    match = re.search(r"\b(\d{6})\b", plain)
    assert match, "Expected 6-digit OTP code in Mailpit email body"
    return match.group(1)


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


def _scroll_click(driver, element) -> None:
    driver.execute_script("arguments[0].scrollIntoView({block: 'center', inline: 'nearest'});", element)
    time.sleep(0.12)
    try:
        element.click()
    except Exception:
        driver.execute_script("arguments[0].click();", element)


def _sign_out(driver) -> None:
    logout_btns = driver.find_elements(By.XPATH, "//form[contains(@action, '/logout')]//button")
    assert logout_btns, "Expected sign-out button in workspace layout."
    driver.execute_script("arguments[0].click();", logout_btns[0])
    WebDriverWait(driver, 10).until(EC.url_contains("/login"))


def _wait_body_contains(driver, text: str, timeout: int = 10, case_insensitive: bool = False) -> None:
    target = text.lower() if case_insensitive else text

    def _check(d):
        body_text = d.find_element(By.TAG_NAME, "body").text
        return target in (body_text.lower() if case_insensitive else body_text)

    WebDriverWait(
        driver,
        timeout,
        ignored_exceptions=(StaleElementReferenceException, WebDriverException),
    ).until(_check)


def _get_activity_highwater() -> int:
    conn = AuthHelper.get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("SELECT COALESCE(MAX(id), 0) FROM activity_logs")
            row = cursor.fetchone()
            return int(row[0] if isinstance(row, tuple) else list(row.values())[0])
    finally:
        conn.close()


def _create_temp_employee_user() -> Optional[Dict[str, Any]]:
    suffix = uuid.uuid4().hex[:8]
    email = f"pub.rec.{suffix}@company.test"
    username = f"pub_rec_{suffix}"
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


def _cleanup_test_state(
    user_ids: List[int],
    ticket_ids: List[int],
    mailpit_ids: List[str],
    log_highwater: int,
) -> None:
    att_rel_paths: List[str] = []
    try:
        conn = AuthHelper.get_db_connection()
        try:
            with conn.cursor() as cursor:
                if ticket_ids:
                    placeholders = ",".join(["%s"] * len(ticket_ids))
                    cursor.execute(
                        f"SELECT storage_relative_path FROM support_attachments WHERE ticket_id IN ({placeholders})",
                        ticket_ids,
                    )
                    for row in cursor.fetchall() or []:
                        rel = row[0] if isinstance(row, tuple) else row.get("storage_relative_path")
                        if rel:
                            att_rel_paths.append(str(rel))
                    cursor.execute(
                        f"DELETE FROM public_support_tracking_tokens WHERE ticket_id IN ({placeholders})",
                        ticket_ids,
                    )
                    cursor.execute(
                        f"DELETE FROM support_sensitive_access_logs WHERE ticket_id IN ({placeholders})",
                        ticket_ids,
                    )
                    cursor.execute(
                        f"DELETE FROM notifications WHERE entity_id IN ({placeholders}) AND event_type LIKE %s",
                        [*ticket_ids, "support_%"],
                    )
                    cursor.execute(
                        f"DELETE FROM support_tickets WHERE id IN ({placeholders})",
                        ticket_ids,
                    )
                    cursor.execute(
                        """
                        UPDATE public_support_ticket_sequences ps
                        SET last_sequence = COALESCE((
                            SELECT MAX(CAST(SUBSTRING_INDEX(st.ticket_ref, '-', -1) AS UNSIGNED))
                            FROM support_tickets st
                            WHERE st.is_public_ticket = 1
                              AND YEAR(st.created_at) = ps.ticket_year
                        ), 0)
                        """
                    )
                if user_ids:
                    u_placeholders = ",".join(["%s"] * len(user_ids))
                    cursor.execute(f"DELETE FROM password_resets WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM trusted_devices WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM user_auth_security_states WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM email_otps WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM realtime_tickets WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM notifications WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM activity_logs WHERE user_id IN ({u_placeholders})", user_ids)
                    cursor.execute(f"DELETE FROM users WHERE id IN ({u_placeholders})", user_ids)
                if log_highwater > 0:
                    cursor.execute(
                        """
                        DELETE FROM activity_logs
                        WHERE id > %s
                          AND entity_type IN ('PASSWORD_RESET', 'PUBLIC_SUPPORT', 'support_tickets')
                        """,
                        (log_highwater,),
                    )
            conn.commit()
        finally:
            conn.close()
    except Exception as exc:
        print(f"[test_public_auth_support_and_recovery] DB cleanup note: {exc}")

    repo_root = Path(__file__).resolve().parents[2]
    repo_storage = repo_root / "storage" / "support_attachments"
    for tid in ticket_ids:
        att_dir = repo_storage / str(tid)
        if att_dir.exists():
            shutil.rmtree(att_dir, ignore_errors=True)
    upload_root = repo_root / "storage" / "uploads" / "support_attachments"
    for rel in att_rel_paths:
        rel_clean = rel.lstrip("/\\")
        for base_dir in (repo_root / "storage", repo_root / "storage" / "uploads", upload_root):
            candidate = (base_dir / rel_clean).resolve()
            if base_dir.resolve() in candidate.parents and candidate.is_file():
                candidate.unlink(missing_ok=True)
                parent = candidate.parent
                while parent != base_dir.resolve() and base_dir.resolve() in parent.parents:
                    try:
                        parent.rmdir()
                    except OSError:
                        break
                    parent = parent.parent

    if mailpit_ids:
        try:
            requests.delete(
                f"{settings.MAILPIT_API_URL}/messages",
                json={"ids": mailpit_ids},
                timeout=5,
            )
        except Exception as exc:
            print(f"[test_public_auth_support_and_recovery] Mailpit cleanup note: {exc}")


@pytest.mark.auth
@pytest.mark.support
class TestPublicHelpNavigationAndResponsiveUi:
    """Validates unauthenticated entry points, 4 assistance options, Dark Mode, and 390x844 layout."""

    def test_public_help_entry_points_theme_toggle_and_mobile_layout(self, driver):
        try:
            base = BasePage(driver)
            _set_viewport(driver, 1440, 900)

            # Landing page footer links & updated Three-Role Authorization card
            base.open("/")
            landing_text = driver.find_element(By.TAG_NAME, "body").text
            assert "Three-Role Authorization" in landing_text
            assert driver.find_elements(By.CSS_SELECTOR, "a[href$='/auth/help']")

            # Login gateway compact help strip & inside-modal links
            login_page = LoginPage(driver).open()
            assert driver.find_elements(By.CSS_SELECTOR, ".auth-help-links a[href$='/auth/help']")
            assert driver.find_elements(By.CSS_SELECTOR, ".auth-help-links a[href$='/forgot-password']")
            assert driver.find_elements(By.CSS_SELECTOR, ".auth-help-links a[href$='/auth/help/otp']")
            assert driver.find_elements(By.CSS_SELECTOR, ".auth-help-links a[href$='/auth/support/track']")

            login_page.ensure_modal_open("employee")
            assert driver.find_elements(By.CSS_SELECTOR, "#loginModalDialog a[href$='/forgot-password']")
            assert driver.find_elements(By.CSS_SELECTOR, ".modal-help-strip a[href$='/auth/support/report']")

            # Help Hub with all 4 core assistance options
            base.open("/auth/help")
            help_text = driver.find_element(By.TAG_NAME, "body").text
            for heading in [
                "Forgot Password",
                "OTP Verification Problems",
                "Unable to Sign In",
                "Contact Support",
                "Track an Existing Request",
            ]:
                assert heading in help_text, f"Missing '{heading}' option on /auth/help"

            # Verify Dark Mode toggle on standalone public support shell
            settings.SCREENSHOTS_DIR.mkdir(parents=True, exist_ok=True)
            toggle = driver.find_element(By.ID, "themeToggleBtn")
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / "public_help_light_1440.png"))
            toggle.click()
            time.sleep(0.2)
            assert driver.execute_script("return document.documentElement.getAttribute('data-theme');") == "dark"
            driver.save_screenshot(str(settings.SCREENSHOTS_DIR / "public_help_dark_1440.png"))
            toggle.click()
            time.sleep(0.15)
            assert driver.execute_script("return document.documentElement.getAttribute('data-theme');") == "light"

            # Mobile 390x844 overflow checks across public assistance screens
            _set_viewport(driver, 390, 844)
            for label, route in [
                ("Login", "/login"),
                ("Help Hub", "/auth/help"),
                ("OTP Guide", "/auth/help/otp"),
                ("Forgot Password", "/forgot-password"),
                ("Report Issue", "/auth/support/report"),
                ("Track Request", "/auth/support/track"),
            ]:
                base.open(route)
                _assert_zero_horizontal_overflow(driver, label, 390, 844)
        finally:
            if hasattr(driver, "execute_cdp_cmd"):
                try:
                    driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
                except Exception:
                    pass


@pytest.mark.auth
class TestPublicPasswordRecoveryEndToEnd:
    """Verifies Forgot Password -> Mailpit link -> Reset Password -> Device Revocation -> Fresh OTP."""

    def test_forgot_reset_revokes_trusted_device_and_forces_fresh_otp(self, driver):
        temp_user = _create_temp_employee_user()
        if temp_user is None:
            pytest.skip(f"Seeded reference account {settings.EMP_EMAIL} missing.")

        log_hw = _get_activity_highwater()
        user_id = int(temp_user["id"])
        email = str(temp_user["email"])
        old_password = str(temp_user["password"])
        new_password = f"Nexora!E2E#{uuid.uuid4().hex[:4]}"
        seen_mail_ids: set[str] = set()
        created_mail_ids: List[str] = []

        try:
            # 1. Authenticate normally and register browser as Trusted Device
            login_page = LoginPage(driver).open()
            login_page.login(email, old_password, role="employee")
            WebDriverWait(driver, 10).until(EC.url_contains("/verify-otp"))

            otp_detail_1, mail_id_1 = _wait_for_mail_matching(email, "Your Sign-In Verification Code", seen_mail_ids)
            seen_mail_ids.add(mail_id_1)
            created_mail_ids.append(mail_id_1)
            otp_code_1 = _extract_otp_code(otp_detail_1)

            remember_cb = WebDriverWait(driver, 8).until(EC.visibility_of_element_located((By.ID, "rememberDeviceCheckbox")))
            remember_cb.click()
            for idx, digit in enumerate(otp_code_1):
                driver.find_element(By.ID, f"otp_digit_{idx}").send_keys(digit)

            try:
                WebDriverWait(driver, 5).until(EC.url_contains("/employee/dashboard"))
            except TimeoutException:
                driver.execute_script("arguments[0].click();", driver.find_element(By.ID, "verifySubmitBtn"))
                WebDriverWait(driver, 10).until(EC.url_contains("/employee/dashboard"))

            assert driver.get_cookie("nexora_td") is not None

            # Sign out, prove trusted bypass works before reset, then sign back out
            _sign_out(driver)
            login_page = LoginPage(driver).open()
            login_page.login(email, old_password, role="employee")
            WebDriverWait(driver, 10).until(EC.url_contains("/employee/dashboard"))
            _sign_out(driver)

            # 2. Request Forgot Password for unknown email -> uniform anti-enumeration banner
            base = BasePage(driver)
            base.open("/forgot-password")
            driver.find_element(By.ID, "forgotEmail").send_keys(f"unknown.{uuid.uuid4().hex[:6]}@company.test")
            _scroll_click(driver, driver.find_element(By.ID, "forgotSubmitBtn"))
            _wait_body_contains(driver, "If an account matches the information provided")

            # 3. Request Forgot Password for temp active account -> identically generic banner + Mailpit link
            driver.find_element(By.ID, "forgotEmail").send_keys(email)
            _scroll_click(driver, driver.find_element(By.ID, "forgotSubmitBtn"))
            _wait_body_contains(driver, "If an account matches the information provided")

            reset_mail, reset_mail_id = _wait_for_mail_matching(email, "Password Reset Instructions", seen_mail_ids)
            seen_mail_ids.add(reset_mail_id)
            created_mail_ids.append(reset_mail_id)
            reset_url = _extract_token_url(reset_mail, "/reset-password")

            # 4. Complete password reset on tokenized page
            driver.get(reset_url)
            WebDriverWait(driver, 10).until(EC.visibility_of_element_located((By.ID, "resetNewPassword")))
            driver.find_element(By.ID, "resetNewPassword").send_keys(new_password)
            driver.find_element(By.ID, "resetConfirmPassword").send_keys(new_password)
            _scroll_click(driver, driver.find_element(By.ID, "resetSubmitBtn"))

            WebDriverWait(driver, 12).until(EC.url_contains("/login"))
            _wait_body_contains(driver, "Please sign in with your new password")

            # Token replay must fail cleanly
            driver.get(reset_url)
            _wait_body_contains(driver, "invalid, has expired, or has already been used", timeout=8, case_insensitive=True)

            # 5. Old password fails; new password requires FRESH Email OTP even on previously trusted browser
            login_page = LoginPage(driver).open()
            login_page.login(email, old_password, role="employee")
            WebDriverWait(driver, 8).until(lambda d: "/login" in d.current_url)

            login_page = LoginPage(driver).open()
            login_page.login(email, new_password, role="employee")
            WebDriverWait(driver, 10).until(EC.url_contains("/verify-otp"))

            otp_detail_2, mail_id_2 = _wait_for_mail_matching(email, "Your Sign-In Verification Code", seen_mail_ids)
            seen_mail_ids.add(mail_id_2)
            created_mail_ids.append(mail_id_2)
            otp_code_2 = _extract_otp_code(otp_detail_2)

            for idx, digit in enumerate(otp_code_2):
                driver.find_element(By.ID, f"otp_digit_{idx}").send_keys(digit)

            try:
                WebDriverWait(driver, 5).until(EC.url_contains("/employee/dashboard"))
            except TimeoutException:
                driver.execute_script("arguments[0].click();", driver.find_element(By.ID, "verifySubmitBtn"))
                WebDriverWait(driver, 10).until(EC.url_contains("/employee/dashboard"))

            # Clean up security alert emails addressed to temp user as well
            for extra in _fetch_mailpit_messages_for(email):
                mid = str(extra.get("ID") or "")
                if mid and mid not in created_mail_ids:
                    created_mail_ids.append(mid)
        finally:
            _cleanup_test_state([user_id], [], created_mail_ids, log_hw)


@pytest.mark.support
class TestPublicSupportRequestAndTrackerEndToEnd:
    """Verifies public AUTH-* ticket intake, attachment upload, Mailpit tracker link, HR triage, and redaction."""

    def test_public_support_intake_hr_triage_and_redacted_tracker(self, driver):
        log_hw = _get_activity_highwater()
        tag = uuid.uuid4().hex[:8].upper()
        req_email = f"pub.ticket.{tag.lower()}@company.test"
        subject = f"E2E OTP Email Missing ({tag})"
        internal_secret = f"INTERNAL_AUTH_MEMO_{tag}"
        hr_public_reply = f"Verified mail relay flushed; please retry OTP login ({tag})."
        requester_follow_up = f"Retried sign-in cleanly and OTP worked ({tag})."
        seen_mail_ids: set[str] = set()
        created_mail_ids: List[str] = []
        created_ticket_ids: List[int] = []

        tmp_dir = Path(tempfile.mkdtemp(prefix="nexora_pub_e2e_"))
        png_path = tmp_dir / f"login_err_{tag}.png"
        png_path.write_bytes(MINIMAL_PNG_BYTES)

        try:
            base = BasePage(driver)
            base.open("/auth/support/report")

            driver.find_element(By.ID, "publicReqName").send_keys("Liza Magbanua")
            driver.find_element(By.ID, "publicReqEmail").send_keys(req_email)
            Select(driver.find_element(By.ID, "publicCategory")).select_by_value("OTP Delivery Problem")
            driver.find_element(By.ID, "publicIssueDatetime").send_keys("Today 08:40 AM PHT")
            driver.find_element(By.ID, "publicSubject").send_keys(subject)
            driver.find_element(By.ID, "publicDescription").send_keys(
                "Entered password successfully at Employee portal login screen, but verification email never arrived."
            )
            driver.find_element(By.ID, "publicAttachments").send_keys(str(png_path))

            _scroll_click(driver, driver.find_element(By.ID, "publicSupportSubmitBtn"))
            ref_el = WebDriverWait(driver, 12).until(EC.visibility_of_element_located((By.ID, "publicTicketRefDisplay")))
            ticket_ref = ref_el.text.strip()
            assert re.match(r"^AUTH-\d{4}-\d{5}$", ticket_ref), f"Unexpected ticket ref: {ticket_ref}"

            # Lookup DB ID for clean teardown
            conn = AuthHelper.get_db_connection()
            try:
                with conn.cursor() as cursor:
                    cursor.execute("SELECT id FROM support_tickets WHERE ticket_ref = %s LIMIT 1", (ticket_ref,))
                    row = cursor.fetchone()
                    assert row is not None
                    ticket_id = int(row[0] if isinstance(row, tuple) else row["id"])
                    created_ticket_ids.append(ticket_id)
            finally:
                conn.close()

            # Retrieve private tracking URL from Mailpit confirmation email
            conf_mail, conf_mail_id = _wait_for_mail_matching(req_email, ticket_ref, seen_mail_ids)
            seen_mail_ids.add(conf_mail_id)
            created_mail_ids.append(conf_mail_id)
            track_url = _extract_token_url(conf_mail, "/auth/support/track")

            driver.get(track_url)
            _wait_body_contains(driver, ticket_ref)
            assert png_path.name in driver.find_element(By.TAG_NAME, "body").text

            # Sign in as HR and triage public AUTH-* request in HR Support Inbox
            AuthHelper.login_as_hr(driver)
            base.open("/hr/support?scope=public_auth")
            inbox_text = driver.find_element(By.TAG_NAME, "body").text
            assert ticket_ref in inbox_text
            assert "Public Login Issue" in inbox_text

            base.open(f"/hr/support/{ticket_id}")
            assert "Public Login Assistance" in driver.find_element(By.TAG_NAME, "body").text

            # Internal HR note (must NEVER leak to public tracker)
            internal_radio = driver.find_element(By.CSS_SELECTOR, 'input[name="response_mode"][value="internal_note"]')
            driver.execute_script("arguments[0].click();", internal_radio)
            driver.find_element(By.ID, "hrResponseBody").send_keys(internal_secret)
            _scroll_click(driver, driver.find_element(By.ID, "hrResponseSubmitBtn"))
            _wait_body_contains(driver, internal_secret)

            # Public visible HR reply requesting requester confirmation
            req_info_radio = driver.find_element(By.CSS_SELECTOR, 'input[name="response_mode"][value="request_info"]')
            driver.execute_script("arguments[0].click();", req_info_radio)
            driver.find_element(By.ID, "hrResponseBody").send_keys(hr_public_reply)
            _scroll_click(driver, driver.find_element(By.ID, "hrResponseSubmitBtn"))
            _wait_body_contains(driver, hr_public_reply)

            # Confirm Mailpit notification sent to public requester for HR response
            update_mail, update_mail_id = _wait_for_mail_matching(req_email, ticket_ref, seen_mail_ids)
            seen_mail_ids.add(update_mail_id)
            created_mail_ids.append(update_mail_id)
            assert "Additional Information Requested" in f"{update_mail.get('Text') or ''}{update_mail.get('HTML') or ''}"

            # Sign out HR -> visit public tracker as unauthenticated user
            _sign_out(driver)
            driver.get(track_url)
            _wait_body_contains(driver, hr_public_reply)
            assert "Waiting for Requester" in driver.find_element(By.TAG_NAME, "body").text
            assert internal_secret not in driver.page_source, (
                "SECURITY VIOLATION: Internal HR note leaked to public support tracker!"
            )

            # Submit unauthenticated follow-up reply -> moves back to Under Review
            driver.find_element(By.ID, "publicReplyBody").send_keys(requester_follow_up)
            _scroll_click(driver, driver.find_element(By.XPATH, '//form[contains(@action, "/auth/support/track/reply")]//button[@type="submit"]'))
            _wait_body_contains(driver, requester_follow_up)
            assert "Under Review" in driver.find_element(By.TAG_NAME, "body").text

            for extra in _fetch_mailpit_messages_for(req_email):
                mid = str(extra.get("ID") or "")
                if mid and mid not in created_mail_ids:
                    created_mail_ids.append(mid)
        finally:
            shutil.rmtree(tmp_dir, ignore_errors=True)
            _cleanup_test_state([], created_ticket_ids, created_mail_ids, log_hw)
