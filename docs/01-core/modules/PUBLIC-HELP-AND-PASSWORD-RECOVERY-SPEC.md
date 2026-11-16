# NEXORA HR — Public Help & Support, Password Recovery, and Login Assistance Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Specification Baseline:** Comprehensive Development Plan v3.1 (aligned with ADR-012 3-Role RBAC)  
**Document Type:** Approved Public Auth Assistance & Recovery Addendum  
**Date:** November 2026

---

## 1. Purpose & Security Objective

Previously, unauthenticated users experiencing forgotten passwords, missing or rejected OTP codes, locked accounts, or sign-in errors had no self-service recovery or pre-login support path. Under this specification, NEXORA HR adds unauthenticated **Help & Support**, **Password Recovery**, **OTP Troubleshooting**, and **Public Login Assistance (`AUTH-YYYY-NNNNN`)** across `/`, `/login`, and `/verify-otp`, while preserving the existing Bcrypt cost-12 hashing, Email OTP challenge, PHPMailer/Mailpit transport, and confidential workplace complaint isolation.

---

## 2. Public Help Hub & Entry Points

* **Entry Points:**
  * Landing page (`/`) footer links (`Help & Support`, `Recover Password`, `Track Support Ticket`) and updated `Three-Role Authorization` card.
  * Login gateway (`/login`) compact footer strip (`.auth-help-links`), `Forgot Password?` link inside the modal password label row, and `.modal-help-strip` links inside the sign-in modal.
  * OTP verification screen (`/verify-otp`) `.otp-help-strip` linking `/auth/help/otp` and `/auth/support/report?category=OTP+Delivery+Problem`.
* **Routes (`public/index.php` -> `App\Controllers\PublicAuthSupportController`):**
  * `GET /auth/help`: Hub presenting **Forgot Password**, **OTP Verification Problems**, **Unable to Sign In**, and **Contact Support**, plus public tracker lookup (`ticket_ref` + tracking token).
  * `GET /auth/help/otp`: OTP delivery troubleshooting, TOTP backup note, cooldown guidance, lost email/device recovery guidance, and direct handoff to pre-login support.
  * `GET|POST /forgot-password`, `GET|POST /reset-password`
  * `GET|POST /auth/support/report`, `GET|POST /auth/support/track`, `POST /auth/support/track/reply`, `GET /auth/support/attachments/{id}`

---

## 3. Password Recovery Architecture

Implemented across `App\Models\PasswordReset`, `App\Services\PasswordRecoveryService`, `App\Services\PasswordPolicy`, and `App\Controllers\PublicAuthSupportController`:

1. **Anti-Enumeration & Rate Limiting:**
   * `POST /forgot-password` enforces CSRF (`CsrfMiddleware`), per-email (`3` per `15` min) and per-IP (`10` per `15` min) rate limits via `activity_logs` (`entity_type = 'PASSWORD_RESET'`), normalizes timing across eligible vs. ineligible/unknown emails, and always renders the identical generic response (`"If an account matches the information provided..."`).
   * Accounts ineligible for reset (non-existent, `users.status !== 'Active'`, or linked `employees.employment_status !== 'Active'`) never receive reset emails.
2. **CSPRNG Split-Token Design & 15-Minute Expiration:**
   * Token format: `selector.verifier` (`32`-char hex selector + `64`-char hex verifier via `random_bytes()`).
   * Stored in `password_resets` (`token_hash` via `HMAC-SHA256`, `expires_at` computed with MySQL clock `NOW() + INTERVAL 15 MINUTE`, `consumed_at`, `invalidated_at`).
   * Delivery runs synchronously via `MailService` (`PHPMailer` / Mailpit) so link issuance is immediate.
3. **Password Validation & Post-Reset Hardening:**
   * Enforces `PasswordPolicy::validateForRecovery()` (`8`–`72` chars, upper/lower/digit/special character requirements, confirmation match, rejection of email/username substrings of at least 4 characters), and hashes with Bcrypt cost `12`.
   * Inside one DB transaction: consumes the token atomically (`consumeTokenAtomically()`), invalidates prior active reset tokens for that user, updates `users.password`, revokes all `trusted_devices`, invalidates pending `email_otps` and `realtime_tickets`, clears active session (`destroyActiveUserSessions()`), sets `user_auth_security_states.require_otp_next_login = 1` (`password_changed`), sends a security confirmation alert without exposing secrets, and redirects to `/login` with no automatic login.

---

## 4. Public Login Assistance & Tokenized Ticket Tracker (`AUTH-YYYY-NNNNN`)

Implemented across `config/support.php`, `App\Models\PublicSupportTrackingToken`, `App\Models\SupportTicket`, `App\Services\PublicAuthSupportService`, `App\Services\SupportWorkflowService`, `App\Services\SupportNotificationService`, and `App\Controllers\HrSupportController`:

* **Categories (`config/support.php` -> `public_auth_categories`):** `Unable to Sign In`, `Account Locked or Disabled`, `OTP Delivery Problem`, `Invalid OTP`, `Password Recovery Problem`, `Login Page Error`, and `Other Technical Issue`.
* **Isolation from Confidential Complaints:**
  * Public requests use `public_support_ticket_sequences` (`last_sequence`) to mint `AUTH-YYYY-NNNNN`, and persist into `support_tickets` with `employee_id = NULL`, `submitted_by_user_id = NULL`, `support_channel = 'public_auth'`, `is_public_ticket = 1`, `is_confidential = 0`, `requester_name`, `requester_email`, optional `matched_user_id` (kept strictly server/HR-internal), `public_issue_datetime`, and `public_client_env`.
  * Protected by `CsrfMiddleware`, honeypot validation, IP (`5` / `15` min) & email (`3` / `15` min) limits (`config/support.php`), and strict attachment allowlist (`pdf`, `jpg`, `png`, `webp`, `txt`, 5 MB max, up to 3 files) via `SupportAttachmentService`.
* **Redacted Public Tracker & HR Triage:**
  * Confirmation emails issue a secret `selector.verifier` tracking URL (`/auth/support/track?ref=...&token=...`, stored HMAC-hashed in `public_support_tracking_tokens`, `30`-day TTL).
  * Public tracking strictly omits internal HR notes (`is_internal_note = 1`), internal-only attachments, HR/Admin usernames, and `matched_user_id`, renders status `"Waiting for Requester"` when ticket status is `Waiting for Employee`, and accepts requester follow-up replies (`sender_role = 'public_requester'`) moving `Waiting for Employee` back to `Under Review`.
  * HR & Admin filter pre-login tickets via `/hr/support?scope=public_auth`, view requester context inside `/hr/support/{id}`, and trigger synchronous email updates to `requester_email` on public HR replies/status changes.

---

## 5. Database Entities

Defined identically across `database/migrations/2026_11_19_create_password_resets_and_public_support.sql`, `database/schema.sql`, and `docker/mysql/init/01-schema.sql`:
1. `password_resets`
2. `public_support_ticket_sequences`
3. `public_support_tracking_tokens`
4. Extended `support_tickets` (`support_channel`, `is_public_ticket`, `requester_name`, `requester_email`, `matched_user_id`, `public_issue_datetime`, `public_client_env`) & `support_messages.sender_role` (`public_requester`).

---

## 6. Verification

* **Database & Parity:** `tests/Unit/Database/InitSchemaParityTest.php`
* **Unit & Feature Security:** `tests/Unit/Security/PasswordRecoveryServiceTest.php`, `tests/Feature/PublicAuthSupportAndRecoveryTest.php`
* **End-to-End Selenium + Mailpit:** `e2e-tests/tests/test_public_auth_support_and_recovery.py` alongside `test_login.py`, `test_otp.py`, `test_smart_otp_trusted_devices.py`, and `test_support_module.py`
* **Quality Gates:** PSR-12 baseline check (`php bin/check-phpcs-baseline.php`), PHPStan analysis, and `impeccable` Light/Dark UI & mobile audit (`public-support.css`, `otp.css`, `auth.css`).
