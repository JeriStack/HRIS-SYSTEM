# Authentication & Session Security Design
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 2.0  
**Status:** Approved Implementation Architecture  

---

## 1. Authentication Lifecycle & Architecture

The system utilizes native PHP session handling backed by MySQL credentials and secure password hashing.

```
[ Visitor ]
    │
    ▼ (POST /login)
[ CSRF Verification (CsrfMiddleware / verify_csrf_token) ]
    │ (Success)
    ▼
[ Parameterized Lookup (User::findByUsername) ]
    │
    ├─► Account Missing / Inactive ──► [ Generic Error (Prevent Enumeration) ]
    │
    ├─► Associated Employee Inactive ─► [ Specific Inactive Notice ]
    │
    ▼
[ Password Verification (password_verify) ]
    │
    ├─► Hash Mismatch ───────────────► [ Generic Error + Audit Log ]
    │
    ▼ (Match)
[ Password Rehashing Check (password_needs_rehash) ]
    │
    ▼
[ Session Regeneration (session_regenerate_id(true)) ]
    │
    ▼
[ Store Minimal Identity in $_SESSION['user'] ]
    │
    ▼
[ Audit Trail Insert (activity_logs: AUTH_LOGIN) ]
    │
    ▼
[ Role-Directed Redirection (/dashboard ──► /admin/dashboard | /hr/dashboard | /employee/dashboard) ]
```

---

## 2. Password Hashing & Rehashing Policy

1. **Algorithm:** Standard `PASSWORD_BCRYPT` with cost factor 12 (or default `PASSWORD_DEFAULT`).
2. **Storage:** Only computed hashes (`VARCHAR(255)`) are stored in `users.password`. Plaintext passwords are never logged, stored in sessions, or cached in temporary variables.
3. **Transparent Rehashing:** On successful authentication, `password_needs_rehash($hash, PASSWORD_DEFAULT)` is evaluated. If the algorithm or cost parameter changes, a new hash is computed and updated in the database without disrupting the user experience.

---

## 3. Session Security Configuration

Session lifecycle is managed centrally via `Core\Session::start()`:

```php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_set_cookie_params([
    'lifetime' => 0,        // Expires when browser closes; inactivity enforced server-side
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps, // True when HTTPS is enabled
    'httponly' => true,     // Inaccessible to JavaScript (XSS mitigation)
    'samesite' => 'Lax',    // Cross-Site Request Forgery (CSRF) mitigation
]);
```

### Inactivity Expiration
- Configured default threshold: **30 minutes (1,800 seconds)** via `config('config.session_lifetime', 1800)`.
- On every request, `time() - $_SESSION['last_activity']` is evaluated.
- If duration exceeds the threshold, `$_SESSION['user']` is unset, the session ID regenerated, and the user redirected to `/login` with an informative expiration notification banner.

### Session Fixation Mitigation
- Upon successful authentication, `Session::regenerate(true)` generates a new session identifier and destroys the pre-authentication session data.

---

## 4. Session Payload Schema

Only non-sensitive, authorization-critical information is stored in `$_SESSION['user']`:

```php
$_SESSION['user'] = [
    'id'          => 1,                 // Primary User ID
    'username'    => 'admin',           // Login Username
    'role'        => 'admin',           // Trusted Role: 'admin' | 'hr' | 'employee'
    'employee_id' => 1,                 // Associated employees.id (or null)
    'employee_no' => 'EMP001',          // Associated Employee Code
    'name'        => 'Eleanor Vance',   // Display Name
    'email'       => 'eleanor@co.local',// Email Address
    'department'  => 'Executive',       // Department Name
    'position'    => 'CEO',             // Position Title
    'login_time'  => 1760250000,        // Timestamp of Login
];
$_SESSION['last_activity'] = time();
```

Passwords, password hashes, reset tokens, and database secrets are **strictly excluded** from the session payload.

---

## 5. Logout Lifecycle

1. Logout requires a **POST** request to `/logout` accompanied by a valid CSRF token (`_csrf_token`).
2. An audit log entry (`AUTH_LOGOUT`) is recorded for the active user ID.
3. `Session::destroy()` unsets `$_SESSION`, expires the session cookie with past timestamp (`time() - 42000`), and terminates the server session.
4. A fresh session is initialized to hold the user-friendly confirmation flash message (`Session::flash('success', ...)`).
5. The visitor is redirected to `/login`.
