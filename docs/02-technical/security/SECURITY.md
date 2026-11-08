# NEXORA HR — Security Architecture & Guidelines

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.2  
**Status:** Authoritative Security Specification  
**Date:** November 2026 (ADR-012 Three-Role RBAC & Admin Governance)  
**Document Owner:** Security Engineering & Repository Maintainer  

---

## 1. Security Architecture Overview

NEXORA HR enforces defense-in-depth security across all architectural layers. The system is designed to protect sensitive workforce Personally Identifiable Information (PII), prevent privilege escalation, guarantee transactional integrity, and neutralize common web application vulnerabilities (OWASP Top 10).

```
┌────────────────────────────────────────────────────────────────────────┐
│                        NEXORA HR Security Perimeter                    │
│                                                                        │
│  [Rate Limiting & IP Throttling]                                       │
│          │                                                             │
│          ▼                                                             │
│  [Synchronized CSRF Token Validation (CsrfMiddleware)]                 │
│          │                                                             │
│          ▼                                                             │
│  [Primary Authentication: Bcrypt Cost 12 with Constant-Time Verify]    │
│          │                                                             │
│          ▼                                                             │
│  [Mandatory 2FA: Cryptographic 6-Digit Email OTP / RFC 6238 TOTP]      │
│          │                                                             │
│          ▼                                                             │
│  [Session Security: ID Rotation + SHA-256 auth_fingerprint]            │
│          │                                                             │
│          ▼                                                             │
│  [Continuous Live Account State Verification on Every HTTP Request]    │
│          │                                                             │
│          ▼                                                             │
│  [Strict 3-Role RBAC: 'admin' vs 'hr' vs 'employee' + Ownership Checks]│
│          │                                                             │
│          ▼                                                             │
│  [Data Layer: PDO Prepared Statements + Pessimistic Row-Level Locks]   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Authentication & Multi-Factor Security

### 2.1 Primary Authentication
* **Password Hashing:** Passwords are hashed using standard PHP `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])`. Cost 12 requires ~250ms of CPU time per evaluation, significantly resisting offline brute-force attacks.
* **Timing-Attack Resistance:** Password comparison uses `password_verify()`, which performs constant-time byte comparisons.
* **Automatic Rehashing:** During authentication, `password_needs_rehash()` is inspected to migrate legacy hashes to updated security parameters transparently.

### 2.2 Multi-Factor Authentication (MFA)
* **Email OTP (Default Channel):**
  * Generates a 6-digit numeric token using `random_int(0, 999999)` with zero-padding.
  * Tokens have a strict 10-minute (600 seconds) Time-To-Live (TTL).
  * Hashes tokens prior to storage in `email_otps` using HMAC-SHA256 peppering.
  * Maximum 5 verification attempts allowed per challenge; exceeding 5 attempts invalidates the challenge immediately.
  * Rate-limited to at most 5 OTP generation requests per 15-minute window per account to prevent inbox flooding.
* **TOTP Authenticator (Alternative Channel):**
  * Complies with RFC 6238 standard utilizing HMAC-SHA1 with 30-second time steps.
  * Secret keys are stored encrypted and provisioned via standard `otpauth://` QR codes.
  * Validates current, previous, and next time windows (±30s) to absorb minor clock drift.

### 2.3 Step-Up Authentication
* High-impact administrative actions (e.g., employee status termination, organizational position removal) require Step-Up Authentication.
* If the user's `last_mfa_verified_at` timestamp exceeds 15 minutes (900 seconds), `StepUpAuthService` prompts for password re-entry or fresh OTP confirmation before processing the action.

---

## 3. Session Management & Continuous Verification

### 3.1 Session Lifecycle Security
* **Session ID Regeneration:** `session_regenerate_id(true)` is invoked immediately upon successful primary login and upon successful MFA completion, destroying the pre-authentication session ID to eliminate session fixation.
* **Cookie Flags:** Cookies are configured with `HttpOnly=true` (inaccessible to JavaScript), `Secure=true` (HTTPS only in production), and `SameSite=Lax` or `Strict` (mitigating cross-site request forgery).
* **Inactivity Expiration:** Sessions expire automatically after 30 minutes (1800 seconds) of inactivity.

### 3.2 Continuous State Re-validation (`verifyAuthenticatedAccountState`)
Unlike legacy systems that trust cached session cookies until logout, NEXORA HR validates live account state on **every single authenticated request**:

```php
// Core\Session::verifyAuthenticatedAccountState()
$dbUser = $userModel->findByIdWithAuthState($userId);

// 1. Account status must be 'Active'
// 2. Linked employee record must be 'Active'
// 3. User role must strictly match session role
// 4. Cryptographic fingerprint must match current state
$computedFingerprint = hash('sha256', "{$id}|{$role}|{$status}|{$empStatus}|{$passwordHash}");
if (!hash_equals($sessionUser['auth_fingerprint'], $computedFingerprint)) {
    self::invalidateAuthenticatedUser('Session terminated due to security change.');
    return false;
}
```

**Benefits:**
* **Instant Session Invalidation:** Terminating or deactivating an employee record in HR cuts off their active browser sessions on their immediate next click.
* **Live Profile Synchronization:** Changes to employee names, job titles, or department assignments synchronize to `$_SESSION['user']` instantly without requiring re-login.

---

## 4. Role-Based Access Control (RBAC) & Governance

### 4.1 Authoritative Three-Role Architecture (ADR-012)
NEXORA HR recognizes exactly three application roles:

| Role | Permitted Capabilities | Prohibited Capabilities |
|---|---|---|
| **`admin`** | Full system-wide administrative oversight, Admin dashboard (`/admin/dashboard`), user account provisioning (`/admin/users*`), role assignment, account deactivation/reactivation, system configuration, audit log review, workforce CRUD, leave review/approval, reports. | Bypassing authentication, MFA, CSRF checks, or last-active Admin safety invariants. |
| **`hr`** | Workforce operational CRUD (employees, departments, positions), leave approval queue processing, leave balance management, HR calendar, HR operational reports, HR activity logs. | Application-wide role assignment, user creation/deactivation, Admin provisioning, system security configuration. |
| **`employee`** | Personal self-service dashboard (`/employee/dashboard`), viewing personal profile (`/employee/profile`), viewing personal leave balances, filing personal leave, cancelling own pending requests. | Strictly blocked from viewing other employees' data, administrative consoles, or workforce management tools. |

### 4.2 Controlled Admin Provisioning
* **No Public Admin Registration:** Admin accounts cannot be registered publicly or self-promoted by existing users.
* **CLI Setup Tool:** Initial Administrator bootstrapping is performed strictly via `bin/create_admin_user.php`.
* **Password Complexity:** Passwords must meet or exceed 8 characters with at least one uppercase letter, one lowercase letter, one number, and one special character.
* **Audit Trail:** Bootstrapping creates an explicit audit event (`ADMIN_BOOTSTRAPPED_CLI`) recording IP/host and admin username.
* **Duplicate Protection:** The CLI script refuses to provision additional Admin accounts without explicit `--force` override when an active Admin exists.

### 4.3 Last Active Admin Lockout Protection
To prevent accidental loss of system governance, the application enforces transactional lockout protection:
* An Administrator cannot delete, deactivate, or demote the last remaining active Administrator in `users`.
* Administrators and HR officers cannot self-demote or self-deactivate their own active sessions (`Cannot demote or deactivate your own active account.`).
* Attempting to demote or deactivate the last Administrator halts with a clear security alert: `System Lockout Protection: Cannot demote or deactivate the last remaining active Administrator in the system.`

### 4.4 Record Ownership Validation
All employee self-service endpoints enforce strict ownership validation:
* An employee querying `/employee/profile` or `/employee/leave-balances` can only retrieve records matching their own `$_SESSION['user']['employee_id']`.
* If a query parameter attempts to request another employee ID (e.g., `/employee/profile?emp_id=99`), the controller detects the ownership mismatch and halts execution with **HTTP 403 Forbidden**.

---

## 5. Input Validation, Output Sanitization & Injection Defense

### 5.1 SQL Injection Neutralization
* Direct SQL string concatenation with request parameters is strictly forbidden.
* All database interactions use PDO prepared statements with explicit parameter binding:
  ```php
  $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = :id AND employment_status = :status");
  $stmt->execute(['id' => $empId, 'status' => 'Active']);
  ```
* Identifiers (table and column names) are never accepted directly from user input; they are strictly validated against hardcoded allowlists.

### 5.2 Cross-Site Scripting (XSS) Prevention
* All dynamic content rendered inside HTML templates is sanitized using the global `e()` helper:
  ```php
  function e(?string $value): string {
      return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
  ```
* JSON API responses set strict `Content-Type: application/json; charset=utf-8` and use `json_encode()` with `JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP`.

### 5.3 Cross-Site Request Forgery (CSRF) Protection
* All state-altering HTTP methods (`POST`, `PUT`, `DELETE`) are gated by `CsrfMiddleware`.
* Forms must embed `<?= csrf_field() ?>`.
* JavaScript API clients (`realtime.js`, fetch requests) transmit the token via the `X-CSRF-TOKEN` HTTP header.
* Tokens are generated using `bin2hex(random_bytes(32))` and evaluated via constant-time `hash_equals()`.

---

## 6. Real-Time WebSocket & Data Protection

### 6.1 Single-Use Connection Tickets
* The Ratchet WebSocket server (:8090) does not accept raw session cookies directly.
* Browsers authenticate via a single-use, cryptographically secure ticket obtained via `POST /realtime/ticket`.
* Tickets have a 60-second validity window and are deleted immediately upon consumption.

### 6.2 Personally Identifiable Information (PII) Masking
* WebSocket broadcast payloads strip all sensitive personal data (e.g., phone numbers, home addresses, dates of birth, medical details).
* Payloads are role-tailored: HR users receive high-level organizational alert metadata, while employees receive only their own request status updates.

---

## 7. Audit Logging & Tamper Evidence

Every security-sensitive action is recorded synchronously in the `activity_logs` table:

```sql
INSERT INTO activity_logs (
    user_id, 
    action, 
    details, 
    ip_address, 
    user_agent, 
    entity_type, 
    entity_id, 
    created_at
) VALUES (:user_id, :action, :details, :ip, :ua, :entity, :entity_id, NOW());
```

### Monitored Actions
* `AUTH_LOGIN`: Successful MFA completion.
* `AUTH_FAILED`: Failed password or OTP verification.
* `AUTH_LOCKOUT`: Brute-force threshold exceeded.
* `USER_DEACTIVATED`: User or employee record deactivated.
* `LEAVE_APPROVED` / `LEAVE_REJECTED`: Leave status mutations with approving officer ID.
* `STEP_UP_CHALLENGE`: High-impact operation authorization events.

