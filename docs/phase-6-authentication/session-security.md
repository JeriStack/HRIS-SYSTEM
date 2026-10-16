# Phase 6: Session Security & CSRF Protection Guidelines
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 6 — Authentication and Authorization  
**Implementation Date:** 2026-10-11  
**Status:** Approved & Implemented  

---

## 1. Session Configuration & Hardening

Session handling in the application is managed exclusively through `Core\Session`. Direct, unmonitored calls to `session_start()` in individual views or controllers are prohibited.

### Strict Session Directives:
```php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
```
- `use_strict_mode = 1`: Rejects uninitialized session IDs supplied by user agents and creates a new session ID instead.
- `use_only_cookies = 1`: Disallows session ID transmission via URL query parameters (`PHPSESSID`), mitigating session hijacking and URL leakage.

---

## 2. Cookie Security Attributes

Every session cookie is dispatched with hardened security flags:

```php
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

session_set_cookie_params([
    'lifetime' => 0,        // Expire on browser exit; timeout tracked on server
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps, // True when HTTPS is detected
    'httponly' => true,     // Inaccessible to client-side JavaScript (XSS defense)
    'samesite' => 'Lax',    // Cross-origin request forgery mitigation
]);
```

- **HttpOnly:** Restricts cookie access strictly to HTTP requests. Scripts (`document.cookie`) cannot read the session identifier.
- **SameSite=Lax:** Prevents the browser from sending session cookies on cross-site requests (e.g. following links from external websites to state-changing operations), offering strong CSRF defense at the transport layer.
- **Secure:** Enforced when executing over HTTPS. In local development (`http://localhost`), it adapts dynamically while warning developers to enable SSL before production deployment.

---

## 3. Session Identifier Regeneration (Fixation Defense)

Session fixation attacks occur when an attacker induces a user into authenticating with a known session ID. To eradicate this vulnerability:
1. `session_regenerate_id(true)` is executed immediately after `password_verify()` returns true.
2. The old session file on the server is discarded (`true` parameter).
3. The newly issued session identifier is bound to the user's authenticated state.

---

## 4. Inactivity Timeout (30 Minutes)

In compliance with `DEC-004`, user sessions automatically expire after 30 minutes of inactivity:
- On each authenticated request, `Core\Session::checkInactivityTimeout()` compares `time() - $_SESSION['last_activity']` against the threshold (`1800` seconds).
- If the duration exceeds 1800 seconds:
  1. The user's authentication identity (`$_SESSION['user']`) is cleared.
  2. The session identifier is regenerated.
  3. A warning flash message is queued: *"Your session has expired due to 30 minutes of inactivity. Please sign in again."*
  4. The user is redirected to `/login`.
- If the duration is within limits, `$_SESSION['last_activity']` is updated with `time()`.

---

## 5. Cross-Site Request Forgery (CSRF) Protection

All state-changing operations (`POST`, `PUT`, `DELETE`, `PATCH`) are safeguarded against CSRF attacks:

### Token Generation:
```php
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
```
A cryptographically secure pseudo-random 64-character hexadecimal token is generated using PHP's `random_bytes(32)`.

### Form Inclusion:
Forms include the token using the global helper:
```html
<form method="POST" action="/login">
    <?= csrf_field() ?>
    <!-- Renders <input type="hidden" name="_csrf_token" value="..."> -->
</form>
```

### Verification Pipeline:
- Intercepted by `Core\CsrfMiddleware` or controller validation.
- Verifies submitted token using timing-safe comparison:
  ```php
  hash_equals($_SESSION['_csrf_token'], $submittedToken);
  ```
- If the token is missing, corrupted, or mismatched:
  - For API requests: returns **HTTP 403 Forbidden**.
  - For browser forms: queues a flash error and redirects to `/login`.

---

## 6. Production Deployment Checklist

Prior to production deployment:
- [ ] Configure Apache / Nginx with an active SSL certificate (HTTPS).
- [ ] Ensure `APP_ENV=production` in `.env` to suppress PHP runtime error displays.
- [ ] Verify that `session.cookie_secure` is strictly `1`.
- [ ] Store session files in a restricted system directory outside the web root (`session.save_path`).
