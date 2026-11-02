# NEXORA HR — Authentication, MFA, and Session Security Architecture

**Brand:** NEXORA HR — *People. Processes. Progress.*  
**System:** Employee HRIS & Leave Management System  
**Roles:** HR (`hr`) and Employee (`employee`) strictly (Admin role is forbidden)  
**Specification Version:** Comprehensive Development Plan, Version 3.1  
**Last Updated:** November 2026

---

## 1. Executive Summary

This document specifies the hardened authentication, multi-factor authentication (MFA), session lifecycle, authorization enforcement, and sensitive-operation protection architecture for **NEXORA HR**.

The system strictly enforces a **two-role access control model**:
- **HR (`hr`)**: Workforce management, organizational structure, leave policy and approval workflows, user provisioning and governance.
- **Employee (`employee`)**: Self-service profile viewing, personal leave balances, and leave request submission/cancellation.

Every fresh sign-in across both roles mandates strong primary password verification followed by cryptographically secure **Email OTP** verification. No remembered-device bypasses or client-side authentication trusts are permitted.

---

## 2. End-to-End Authentication Workflow

The authentication lifecycle proceeds through two isolated stages:

```
[ Visitor / Client ]
         │
         ▼ (POST /login with email & password)
[ Step 1: Credential Validation ]
  ├── CSRF validation (POST, JSON body, or X-CSRF-Token header)
  ├── Primary login rate limit check (Account: max 5 / 15m; IP: max 15 / 15m)
  ├── Normalized email lookup (LOWER, TRIM)
  ├── Constant-time password verification via password_verify()
  ├── Transparent password rehashing via password_needs_rehash()
  ├── Account operational status check (`status === 'Active'`)
  ├── Linked employee status check (`employment_status === 'Active'`)
  ├── Strict role validation (strictly 'hr' or 'employee')
  ├── Portal role match check (prevents HR portal sign-in by employee accounts)
  ├── Cross-challenge lockout check (max 10 accumulated failures / 15m)
  ├── OTP issuance rate limit check (max 5 codes / 15m inbox-flooding protection)
  ├── Cryptographically secure 6-digit OTP generation (`random_int(0, 999999)`)
  ├── Atomic challenge persistence (`email_otps` table with row locking)
  ├── Keyed HMAC-SHA256 peppering before password_hash()
  └── Session ID rotation & establishment of pending OTP challenge state
         │
         ▼ (User receives 6-digit code via SMTP email)
[ Step 2: Multi-Factor Verification ]
  ├── Client submits 6-digit code (POST /verify-otp or POST /api/v1/auth/verify-otp)
  ├── Validation of pending challenge session state & 15-minute absolute pending timeout
  ├── Verification of cross-challenge lockout status
  ├── Atomic row lock on active challenge (`SELECT ... FOR UPDATE`)
  ├── Expiration check (5 minutes / 300 seconds)
  ├── Attempt count check (5 attempts maximum per challenge)
  ├── Timing-safe hash comparison (`verifyOtpHash` supporting peppered and legacy hashes)
  ├── On failure: decrement remaining attempts, atomically consume if exhausted
  ├── On success: atomically consume challenge (`consumed_at = NOW()`)
  ├── Re-validate user account and linked employee status
  ├── Session ID regeneration (`session_regenerate_id(true)`)
  ├── Clear all pending challenge state from session
  ├── Compute security fingerprint (`auth_fingerprint`) binding session to account state
  ├── Establish authenticated `user` session payload (recording `login_time` and `last_mfa_verified_at`)
  ├── Audit log recorded (`AUTH_LOGIN`)
  └── Server-directed routing to role dashboard (`/hr/dashboard` or `/employee/dashboard`)
```

---

## 3. Multi-Factor Authentication (MFA) Policy

| Policy Requirement | HR Role | Employee Role | Policy Implementation |
|---|---|---|---|
| Primary Credentials | Required | Required | Minimum 8 characters, hashed with `PASSWORD_DEFAULT` (bcrypt/argon2). |
| Multi-Factor Authentication | Required | Required | Mandatory on every fresh sign-in. |
| Remembered Device Bypass | Prohibited | Prohibited | Every new sign-in requires complete MFA verification. |
| OTP Delivery Method | Email (SMTP) | Email (SMTP) | Delivered to registered employee email address via PHPMailer. |
| Challenge Expiration | 5 Minutes | 5 Minutes | Code is rejected and consumed if `time() > expires_at`. |
| Single-Use Consumption | Enforced | Enforced | Consumed immediately upon successful verification. |
| Maximum Attempts Per Challenge | 5 Attempts | 5 Attempts | Challenge is invalidated upon 5 failed attempts. |
| Rolling Window Lockout | 10 Attempts / 15 Min | 10 Attempts / 15 Min | Prevents bypass via repeated code requests. |
| Inbox-Flooding Protection | 5 Codes / 15 Min | 5 Codes / 15 Min | Capped across initial login and resend requests. |
| Resend Cooldown | 60 Seconds | 60 Seconds | Server-enforced cooldown window between resend requests. |

---

## 4. OTP Security & Cryptographic Controls

1. **Cryptographically Secure Generation**: OTP codes are generated via PHP's `random_int(0, 999999)` and padded to exactly 6 numeric digits (`str_pad(..., 6, '0', STR_PAD_LEFT)`).
2. **Server-Side Keyed HMAC Pepper**: To defeat offline dictionary attacks on the $10^6$ code space in the event of database exposure, codes are peppered with a server-side secret (`OTP_PEPPER_SECRET`) via `hash_hmac('sha256', "{$userId}:{$plainOtp}", $secret)` prior to hashing with `password_hash()`. Verification maintains backward compatibility with legacy hashes.
3. **Atomic Concurrency Protection**: Verification executes inside an isolated database transaction with `FOR UPDATE` row-level locking to prevent race conditions and replay attempts.
4. **Zero Log Disclosure**: Plaintext OTP codes are never logged, stored in session arrays, or exposed in JSON API responses.

---

## 5. Session Management & Cookie Security

Sessions are managed natively by `Core\Session` with defense-in-depth settings:

- **Cookie Flags**:
  - `HttpOnly`: Enforced (`true`) to prevent script access.
  - `Secure`: Dynamically enforced over HTTPS (`$_SERVER['HTTPS']`, port 443, or `X-Forwarded-Proto: https`).
  - `SameSite`: Configured to `Lax` to prevent Cross-Site Request Forgery.
  - `use_strict_mode`: Enabled (`ini_set('session.use_strict_mode', '1')`) to reject uninitialized session IDs.
  - `use_only_cookies`: Enabled (`ini_set('session.use_only_cookies', '1')`).
- **Inactivity Timeout**: Configurable (`SESSION_LIFETIME`, default 1800s / 30 minutes). Background polling requests sending `X-HRIS-Background: 1` do not extend this timer.
- **Absolute Session Lifetime**: Configurable (`SESSION_ABSOLUTE_LIFETIME`, default 43200s / 12 hours). Sessions exceeding this limit are terminated regardless of activity.
- **Session Fixation Defense**: Session identifiers are rotated via `session_regenerate_id(true)` during login, OTP challenge creation, OTP verification, and sign-out.
- **Live Account State Re-validation**: On every authenticated request, `Session::verifyAuthenticatedAccountState()` validates that the account remains active, the linked employee is active, the role has not changed, and the security fingerprint (`auth_fingerprint`) matches. Deactivated or altered accounts are terminated immediately.

---

## 6. Sensitive-Operation Protection (Step-Up Verification)

High-impact administrative and HR actions require step-up verification via `App\Services\StepUpAuthService`:

### Protected Operations
1. **User Provisioning**: Creating new login credentials (`UserController::store`).
2. **Role & Status Governance**: Changing user roles or deactivating accounts (`UserController::update`).
3. **Employee Deactivation**: Soft-deactivating an employee and revoking linked credentials (`EmployeeController::deactivate`, `Api\EmployeeController::deactivate`).
4. **Workforce Status Changes**: Changing employment status via profile update (`EmployeeController::update`, `Api\EmployeeController::update`).

### Verification Mechanism
A protected operation succeeds if:
- The acting user verified MFA or performed a step-up check within the configurable window (`STEP_UP_WINDOW`, default 900s / 15 minutes), OR
- The request supplies the acting user's current password (`current_password`), which is verified server-side. On success, the step-up timestamp is refreshed in the session.

Client-supplied flags or tokens are never trusted for authorization decisions.

---

## 7. Account State Synchronization & Revocation

When an administrative event alters a user's authorization:
1. **Out-of-Band Revocation**: `AccountRevocationService::revokeUser($userId)` immediately revokes all issued WebSocket tickets (`realtime_tickets` table) and invalidates pending OTP challenges (`email_otps` table).
2. **Live Session Invalidation**: Active browser sessions of affected users are detected and destroyed on their next HTTP request by `Session::verifyAuthenticatedAccountState()`.
3. **Employee Synchronization**: Deactivating an employee record automatically updates the associated `users.status` to `Inactive`.

---

## 8. Audit Logging & Security Event Tracking

Security audit trails are maintained in `activity_logs` via `User::logActivity()`:
- `AUTH_LOGIN`: Successful 2FA sign-in.
- `AUTH_FAILED`: Failed primary password or unknown email attempt.
- `AUTH_RATE_LIMITED`: Brute-force threshold exceeded.
- `AUTH_OTP_DISPATCHED`: Verification code issued and sent.
- `AUTH_OTP_FAILED`: Incorrect OTP submitted.
- `AUTH_OTP_EXHAUSTED`: Maximum OTP verification attempts exceeded.
- `AUTH_OTP_LOCKED_OUT`: User locked out from repeated failures.
- `AUTH_LOGOUT`: Explicit user sign-out and session revocation.
- `USER_PROVISIONED`: New user portal account created.
- `USER_ROLE_UPDATED`: Account role or operational status altered.
- `EMPLOYEE_DEACTIVATED`: Employee placed on Inactive status.
- `STEP_UP_VERIFIED`: Sensitive operation re-authenticated.
- `STEP_UP_FAILED`: Incorrect confirmation password during step-up.

Credentials, passwords, OTP values, and encryption secrets are never written to audit logs.

---

## 9. Future MFA Migration Path (TOTP & Passkeys)

The architecture is prepared for seamless expansion to hardware-bound and authenticator-app factors:

### Phase A: Mobile Authenticator App (TOTP - RFC 6238)
- The system already includes the `user_totp` database table (`2026_10_31_create_user_totp_table.sql`) and fully tested cryptographic service (`App\Services\TotpService`).
- **Secret Storage**: Base32 secret keys encrypted at rest using AES-256-GCM (`TotpService::encryptSecret`).
- **Replay Protection**: Timestep tracking (`last_used_timestep`) prevents code replay within the 30-second window.
- **Recovery Codes**: Single-use cryptographic recovery codes hashed with `PASSWORD_DEFAULT`.
- **Migration Strategy**: Allow users to enroll an authenticator app (Google Authenticator, Microsoft Authenticator) in account settings, transitioning their preferred factor from Email OTP to TOTP while retaining Email OTP as an administrative recovery fallback.

### Phase B: FIDO2 / WebAuthn Passkeys
- Support biometric hardware authenticators (Windows Hello, Touch ID, YubiKey).
- Passkey credentials registered per device with public-key cryptography stored in a dedicated `user_passkeys` table.
- Eliminates credential transmission over email or networks, blunting phishing and SIM-swap vectors.

