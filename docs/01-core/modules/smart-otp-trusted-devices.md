# NEXORA HR — Smart OTP Authentication & Trusted Device Management Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Specification Baseline:** Comprehensive Development Plan v3.1 (aligned with ADR-012 3-Role RBAC)  
**Document Type:** Approved Security & Authentication Architecture Addendum  
**Date:** November 2026  

---

## 1. Purpose & Security Objective

Previously, NEXORA HR required a 6-digit Email OTP challenge on every sign-in, even from the employee's everyday personal browser. Under **Smart OTP Authentication**, the system verifies primary credentials, account status, and role portal eligibility first, then checks a cryptographically verifiable browser credential (`nexora_td`):

* **Unrecognized, expired, revoked, or risk-flagged browsers** always complete full Email OTP verification.
* **Verified personal browsers** explicitly opted in via **Remember this device** skip repetitive Email OTP challenges for a role-scoped fixed period (`30` days for Employee; `7` days for HR and Admin).
* **Credentials & PHP session lifecycle stay strictly separated:** Signing out (`POST /logout`) destroys `PHPSESSID` and real-time tickets while preserving `nexora_td`, and trusted-device sign-in sets `last_mfa_verified_at = null` so `StepUpAuthService` still requires current password re-verification before sensitive actions.

---

## 2. Authentication Flow & State Separation

1. Client submits email, password, and selected portal on `/login` (`App\Controllers\AuthController::login` or `/api/v1/auth/login`).
2. Backend validates email/password hash, active account/employee status, and role authorization. **No authenticated session or trusted-device check runs before password verification succeeds.**
3. `TrustedDeviceService::evaluateAndRotateOnLogin()` inspects:
   * User risk state via `AuthRiskService` & `UserAuthSecurityState` (`password_changed`, `email_changed`, `trusted_device_recovery`, `validator_mismatch_suspected_replay`, MFA changes, or privileged multi-signal drift — never forcing step-up solely from an ordinary IP address change).
   * Presence and strict syntax (`/^([a-f0-9]{32})\.([a-f0-9]{64})$/D`) of the `nexora_td` cookie.
   * Database record match by `selector`, active status (`revoked_at IS NULL` and `expires_at > NOW()` using MySQL clock), owner `user_id` match, and constant-time (`hash_equals`) validator HMAC/SHA-256 verification.
4. **If trusted and free of step-up flags:**
   * `TrustedDevice::rotateValidatorAtomically()` replaces `token_hash` atomically while preserving the fixed `expires_at` timestamp, issues a fresh `nexora_td` cookie, and establishes an authenticated session (`login_auth_method = 'trusted_device'`, `last_mfa_verified_at = null`). No OTP email is sent.
   * On validator mismatch (`token_hash` mismatch against an active selector), `markAnomalyAndRevoke()` revokes the row immediately with `revocation_reason = 'validator_mismatch_suspected_replay'`, flags the user via `AuthRiskService`, sends a security alert, and falls back to mandatory Email OTP.
5. **If untrusted or step-up required:**
   * Initiates the standard 6-digit `EmailOtp` challenge (`PHPMailer` / Mailpit), regenerates the pre-auth session ID, surfaces `$stepUpNotice` when triggered by a risk event, and renders `#rememberDeviceCheckbox` (default unchecked) on `/verify-otp`.
   * Only **after** `verifyOtp()` validates the 6-digit OTP and active account state does `registerBrowserAfterOtp()` create/replace the trusted-device record and emit `nexora_td`.

---

## 3. Cryptographic Token & Cookie Design

| Control | Implementation | Source |
|---|---|---|
| Cookie name | `nexora_td` (configurable via `TRUSTED_DEVICE_COOKIE_NAME`) | `config/auth_devices.php` |
| Token structure | `selector.validator` (32-char hex selector `.` 64-char hex validator; whitespace rejected) | `app/services/TrustedDeviceService.php` |
| Server storage | Raw validators never stored; stored hashed with `HMAC-SHA256` (`TRUSTED_DEVICE_PEPPER` / `OTP_PEPPER_SECRET` fallback) or `SHA-256` fallback | `app/services/TrustedDeviceService.php` |
| Comparison & Rotation | `hash_equals()` verification + single-use atomic rotation (`rotateValidatorAtomically()`) on every trusted login | `app/models/TrustedDevice.php` |
| Cookie attributes | `HttpOnly`, `SameSite=Lax`, host-only (`Domain` omitted), `Path=/`, `Secure` on HTTPS (proxy-aware via `\App\Realtime\IpRange::matchesAny()`) | `app/services/TrustedDeviceService.php` |
| Per-user device cap | Max `10` active browsers (`TRUSTED_DEVICE_MAX_PER_USER`); oldest active row evicted (`device_limit_exceeded`) | `app/models/TrustedDevice.php` |
| Fixed trust durations | `30` days (`employee`), `7` days (`hr`), `7` days (`admin`); computed via MySQL `NOW() + INTERVAL ... DAY` without sliding renewal | `config/auth_devices.php`, `app/models/TrustedDevice.php` |

---

## 4. Database Schema & Synchronization

Defined identically in:
* `database/migrations/2026_11_18_create_trusted_devices_and_auth_security.sql`
* `database/schema.sql`
* `docker/mysql/init/01-schema.sql`

### Tables
1. **`trusted_devices`**: Stores `user_id`, unique `selector`, `token_hash`, parsed `device_name` / `browser_name` / `os_name` / truncated `user_agent`, `last_ip`, `trust_days`, `last_used_at`, `created_at`, fixed `expires_at`, `revoked_at`, and `revocation_reason`.
2. **`user_auth_security_states`**: Stores per-user security state (`required_step_up_reason`, timestamps for password/email/recovery/anomaly events) managed by `UserAuthSecurityState` and `AuthRiskService`.

---

## 5. Self-Service Management, Notifications & Recovery

* **Workspace UI (`/account/security`):** Accessible from the sidebar link (`Security & Devices`) across Admin, HR, and Employee workspaces (`AccountSecurityController`, `app/views/account/security.php`, `public/assets/js/account_security.js`).
  * Displays posture summary cards (`Active Trusted Browsers`, `This Browser Status`, `Current Login Session`).
  * Lists remembered & recent browsers with `"This Browser"` pill indicator, `@container`-responsive mobile card layout (`public/assets/css/components.css`), and accessible `<dialog>` confirmations for single-device revocation (`POST /account/security/devices/{id}/revoke`, IDOR-scoped by `user_id`) and bulk revocation (`POST /account/security/devices/revoke-all`).
  * **Lost or Compromised Device Recovery (`POST /account/security/recover`):** Rate-limited (`User::countRecentActions` `STEP_UP_FAILED` check), verifies `current_password`, revokes all active trusted devices (`trusted_device_recovery`), flags mandatory fresh Email OTP on next login, clears `nexora_td`, and notifies the user.
* **Account Credential Integration:** `AdminController::updateAccount()` revokes all trusted devices (`revocation_reason = 'password_changed'`), flags step-up verification, and triggers `AuthSecurityNotificationService::notifyAllDevicesRevoked()` on password change; email changes flag `email_changed` step-up.
* **Audit & Privacy Hygiene:** Activity logs and in-app/email security alerts (`AuthSecurityNotificationService`) never log or include raw selectors, validators, cookies, or OTP codes.

---

## 6. Configuration Variables

Optional environment overrides (defaults active in `config/auth_devices.php`):
* `TRUSTED_DEVICE_COOKIE_NAME=nexora_td`
* `TRUSTED_DEVICE_DAYS_EMPLOYEE=30`
* `TRUSTED_DEVICE_DAYS_HR=7`
* `TRUSTED_DEVICE_DAYS_ADMIN=7`
* `TRUSTED_DEVICE_MAX_PER_USER=10`
* `TRUSTED_DEVICE_PEPPER=` (optional dedicated pepper; falls back to `OTP_PEPPER_SECRET` when unset)

---

## 7. Security Boundaries & Explicit Limitations Disclosure

* **Email OTP is NOT phishing-resistant:** While Smart OTP eliminates unnecessary repeated OTP challenges on verified devices without relying on spoofable browser fingerprints alone, Email OTP itself remains vulnerable to real-time adversary-in-the-middle phishing and email-account compromise compared to WebAuthn / FIDO2 passkeys or hardware security keys.
* **Browser profile scope:** `nexora_td` is a server-hashed, single-use bearer credential bound to one browser profile cookie jar. Users on shared computers must keep **Remember this device** unchecked or revoke the browser via `/account/security` or `/account/security/recover`.
* **Verification:** Covered by `tests/Unit/Database/InitSchemaParityTest.php`, `tests/Unit/Security/TrustedDeviceServiceTest.php`, `tests/Feature/SmartOtpAuthenticationTest.php`, and `e2e-tests/tests/test_smart_otp_trusted_devices.py` (using Mailpit REST API `:8025` for live OTP delivery).
