# Phase 6: Authentication System Architecture & Design
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 6 — Authentication and Authorization  
**Implementation Date:** 2026-10-11  
**Status:** Approved & Verified Live  

---

## 1. Architectural Overview

The Phase 6 authentication architecture enforces server-side identity verification, session lifecycle management, and role-based access control (RBAC) across all protected HRIS workflows. Designed with zero third-party framework dependencies, it extends the custom Model-View-Controller (MVC) and middleware foundation established in Phase 5.

```text
                                HTTP Request (GET/POST)
                                          │
                                          ▼
                                   Core\Session
                        (Cookie Params, Timeout, CSRF)
                                          │
                                          ▼
                                    Core\Router
                                          │
                  ┌───────────────────────┴───────────────────────┐
                  ▼                                               ▼
         Public Route (/login)                         Protected Route (/hr/*, /employee/*)
                  │                                               │
                  │                                        Core\AuthMiddleware
                  │                                      (Is Authenticated?)
                  │                                               │
                  │                                        Core\RoleMiddleware
                  │                                        (Has Required Role?)
                  │                                               │
                  ▼                                               ▼
         AuthController                                   DashboardController / Domain
    (password_verify, CSRF,                               (Ownership Enforcement,
   session_regenerate_id)                                  Parameterized PDO Queries)
                  │                                               │
                  └───────────────────────┬───────────────────────┘
                                          ▼
                                     HTTP Response
```

---

## 2. Authentication Workflow (POST `/login`)

```text
[ Browser POST /login ]
        │
        ▼
1. Validate CSRF Token
   (hash_equals verification against $_SESSION['_csrf_token'])
        │  ├── Mismatch/Missing ──► HTTP 403 / Redirect with Flash Error
        ▼
2. Validate Input Fields
   (Ensure username and password are provided and trimmed)
        │  ├── Missing ────────────► Redirect with Flash Error ("Please provide both...")
        ▼
3. Retrieve User from Database via PDO Prepared Statement
   (Query `users` table joined with `employees`, `departments`, `positions`)
        │  ├── Not Found / Inactive ► Log AUTH_FAILED, Generic Error ("Invalid credentials")
        ▼
4. Verify Employment Status
   (If linked to `employees`, confirm `employment_status = 'Active'`)
        │  ├── Deactivated/Terminated ► Log AUTH_BLOCKED, Flash Error ("Account is inactive")
        ▼
5. Verify Password Hash
   (Native password_verify($password, $user['password']))
        │  ├── Hash Mismatch ──────► Log AUTH_FAILED, Generic Error ("Invalid credentials")
        ▼
6. Transparent Algorithm Rehash
   (Check password_needs_rehash(); update hash via PDO if TRUE)
        │
        ▼
7. Prevent Session Fixation
   (session_regenerate_id(true))
        │
        ▼
8. Populate Authenticated Session State
   ($_SESSION['user'] = [id, username, role, employee_id, employee_no, name, ...])
   (Passwords and password hashes are NEVER stored in session state)
        │
        ▼
9. Audit Logging
   (Write non-sensitive record to `activity_logs` table)
        │
        ▼
10. Role-Directed Redirection
    (Admin/HR ──► /hr/dashboard | Employee ──► /employee/dashboard)
```

---

## 3. Password Hashing & Verification Standards

In compliance with `NFR-SEC-002` and modern cryptographic guidelines:
1. **Hashing Algorithm:** Passwords are generated using PHP's native `password_hash($password, PASSWORD_DEFAULT)` utilizing the BCRYPT algorithm with default cost (10–12).
2. **Hash Storage:** Stored in the `users.password` column (`VARCHAR(255)`), providing ample capacity for current BCRYPT and future Argon2id hashes.
3. **Password Verification:** All authentication passes execute `password_verify($password, $hash)`. Direct string comparison (`==` or `===`) is strictly prohibited.
4. **Algorithmic Rehashing:** On each successful login, `password_needs_rehash($hash, PASSWORD_DEFAULT)` is evaluated. If PHP's default cost or algorithm evolves, the stored hash is transparently updated in the database without disrupting the user.
5. **Zero Credential Logging:** Passwords and password hashes are never written to application logs, session records, or error stack traces.

---

## 4. Session Security & Lifecycle Management

Session management is centralized in `Core\Session`:
- **Strict Mode:** `ini_set('session.use_strict_mode', '1')` prevents uninitialized session identifiers.
- **Cookie Security:**
  - `httponly = true`: Completely mitigates JavaScript access via `document.cookie` (XSS defense).
  - `samesite = 'Lax'`: Mitigates Cross-Site Request Forgery across cross-origin requests.
  - `secure = true (conditional)`: Automatically enabled on HTTPS requests.
  - `lifetime = 0`: Session cookies expire upon browser termination; server-side tracking enforces inactivity timeouts.
- **Inactivity Timeout:** Configured via `config('config.session_lifetime', 1800)` (30 minutes). If `time() - $_SESSION['last_activity'] > 1800`, the user's session payload is purged, the session identifier is regenerated, and a flash expiration notice is queued.
- **Session ID Regeneration:** Executed via `session_regenerate_id(true)` immediately following authentication and expiration to eliminate session fixation vulnerabilities.

---

## 5. Logout Architecture (POST `/logout`)

Logout is treated as a state-changing operation requiring CSRF validation:
1. Validates the submitted `_csrf_token`.
2. Records an `AUTH_LOGOUT` audit event in `activity_logs`.
3. Calls `Core\Session::destroy()`, which wipes `$_SESSION`, deletes the session cookie with past expiration (`time() - 42000`), and terminates the active session storage.
4. Initializes a clean flash session to display `"You have been successfully signed out."` on the login screen.
5. Redirects to `/login`.

---

## 6. Authentication Helper Functions (`core/functions.php`)

| Function | Return Type | Description |
| :--- | :--- | :--- |
| `auth()` | `?array` | Returns the active authenticated user array from session or `null`. |
| `isAuthenticated()` | `bool` | Returns `true` if an authenticated session exists with a valid user ID. |
| `currentUser()` | `?array` | Alias to `auth()`. |
| `currentUserId()` | `?int` | Returns the authenticated user's primary key ID. |
| `currentUserRole()` | `?string` | Returns the authenticated user's role (`'admin'`, `'hr'`, `'employee'`). |
| `hasRole($roles)` | `bool` | Evaluates if the authenticated user possesses any of the specified roles. |
| `csrf_token()` | `string` | Retrieves the cryptographically secure CSRF token for the session. |
| `csrf_field()` | `string` | Generates a hidden HTML input tag containing the CSRF token. |
| `verify_csrf_token($token)` | `bool` | Performs timing-safe verification using `hash_equals()`. |

---

## 7. Error Handling & Account Enumeration Prevention

To prevent user enumeration (`NFR-SEC-006`):
- When an unknown username is supplied, or an invalid password is provided, the application returns the identical generic feedback message:  
  *"Invalid username or password. Please verify your credentials and try again."*
- Timing discrepancies are minimized by ensuring standard database querying occurs before hash verification.
- Internal database exceptions (`PDOException`) are logged to server error logs with sanitized messages and never leaked to the browser.
