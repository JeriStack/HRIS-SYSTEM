# Security Configuration Guide

## Employee HRIS & Leave Management System

### 1. Multi-Tier Security Overview
The Employee HRIS & Leave Management System implements defense-in-depth across the application, session, web server, and database layers to safeguard sensitive human resource records.

---

### 2. Role-Based Access Control (RBAC) Governance
The system enforces strict 3-role separation on the server:
- **`admin` (System Administrator):** System oversight, user account management, role assignment, and security audit logs.
- **`hr` (Human Resources Officer):** Operational employee directory management, department/position creation, and leave approval decisions. Blocked from administrator user governance.
- **`employee` (Self-Service Employee):** Strictly isolated to personal profile and leave balance records. Parameter tampering (IDOR) attacks targeting other employee IDs return HTTP 403 Forbidden.

#### Server-Side Enforcement Rules
- Client-side visibility (hiding buttons or links) is strictly treated as UX presentation, never security.
- Every controller action validates `AuthMiddleware`, `RoleMiddleware`, and parameter ownership before executing data operations.
- Direct object reference tampering (e.g. `?emp_id=5` on `/employee/profile`) explicitly triggers an HTTP 403 response with an ownership violation audit entry.

---

### 3. Session Hardening & Authentication Security
- **Password Hashing:** Passwords hashed with `PASSWORD_BCRYPT` (cost factor 12) or Argon2id.
- **Session ID Regeneration:** `session_regenerate_id(true)` executed upon successful authentication to prevent session fixation.
- **Session Expiration:** Default 30-minute idle expiration (`SESSION_LIFETIME=1800`).
- **Post-Logout Invalidation:** Session cookies destroyed and session data wiped on `POST /logout`.
- **CSRF Token Validation:** All state-modifying requests (`POST`, `PUT`, `DELETE`) require a cryptographically secure CSRF token (`CsrfMiddleware`).

#### Recommended PHP `php.ini` Session Settings in Production
```ini
session.cookie_httponly = 1
session.cookie_secure = 1       ; Enforce HTTPS cookie transmission
session.cookie_samesite = "Lax"
session.use_strict_mode = 1
session.use_only_cookies = 1
```

---

### 4. HTTP Security Headers
Configured in `public/.htaccess`:
- `X-Frame-Options: SAMEORIGIN`: Prevents UI redressing and clickjacking.
- `X-Content-Type-Options: nosniff`: Prevents MIME-type confusion attacks.
- `X-XSS-Protection: 1; mode=block`: Enables browser reflection filter.
- `Referrer-Policy: strict-origin-when-cross-origin`: Restricts referrer leakage.
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`: Blocks unwanted device APIs.

---

### 5. Database Least Privilege Account
In production, do not connect as `root`. Provision a dedicated application database user with restricted permissions:
```sql
CREATE USER 'hris_app'@'127.0.0.1' IDENTIFIED BY 'StrongPassword!2026';
GRANT SELECT, INSERT, UPDATE, DELETE ON hris_db.* TO 'hris_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```
This user cannot execute `DROP TABLE`, `ALTER TABLE`, or administrative database commands.

---

### 6. Directory Access Hardening
All internal directories contain `.htaccess` files containing `Require all denied`:
- `app/`
- `core/`
- `config/`
- `database/`
- `scratch/`

Web server only allows public access to `public/` and static assets.
