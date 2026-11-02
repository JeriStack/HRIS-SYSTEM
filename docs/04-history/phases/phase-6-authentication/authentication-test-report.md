# Phase 6: Authentication & Authorization Security Test Report
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 6 — Authentication and Authorization  
**Test Date:** 2026-10-11  
**Test Environment:** PHP 8.5.9, Apache 2.4 (XAMPP), MySQL 8.x / MariaDB (`hris_db`)  
**Execution Tool:** Automated cURL Test Suite (`scratch/test_phase6_auth.php`)  
**Overall Result:** 11 / 11 Tests Passed (100%)  

---

## 1. Test Execution Summary

| Test ID | Scenario Description | Expected HTTP Status & Behavior | Actual HTTP Status & Behavior | Status |
| :---: | :--- | :--- | :--- | :---: |
| `AUTH-01` | Render Login Page (`GET /login`) | HTTP 200, valid form, CSRF token generated | HTTP 200, valid form, CSRF token generated | **PASSED** |
| `AUTH-02` | Unauthenticated access to HR Route (`GET /hr/dashboard`) | HTTP 302 Redirect to `/login` | HTTP 302 Redirect to `/login` | **PASSED** |
| `AUTH-03` | Unauthenticated access to Employee Route (`GET /employee/dashboard`) | HTTP 302 Redirect to `/login` | HTTP 302 Redirect to `/login` | **PASSED** |
| `AUTH-04` | POST `/login` without CSRF token | HTTP 403 Forbidden (CSRF token validation failure) | HTTP 403 Forbidden, JSON error payload | **PASSED** |
| `AUTH-05` | POST `/login` with incorrect password | HTTP 302 Redirect to `/login`, flash error `"Invalid credentials"` | HTTP 302 Redirect, verified flash error message | **PASSED** |
| `AUTH-06` | POST `/login` with valid Admin credentials (`admin` / `Admin@123`) | HTTP 302 Redirect to `/dashboard` ──► HTTP 200 `/hr/dashboard` | HTTP 302, followed to HTTP 200 HR portal | **PASSED** |
| `AUTH-07` | POST `/login` with valid Employee credentials (`emp004` / `Employee@123`) | HTTP 302 Redirect to `/dashboard` ──► HTTP 200 `/employee/dashboard` | HTTP 302, followed to HTTP 200 Employee portal | **PASSED** |
| `AUTH-08` | Employee attempting to access HR Dashboard (`GET /hr/dashboard`) | **HTTP 403 Forbidden** (Blocked server-side by `RoleMiddleware`) | **HTTP 403 Forbidden**, Access Denied view | **PASSED** |
| `AUTH-09` | Employee attempting to access HR Directory (`GET /hr/employees`) | **HTTP 403 Forbidden** (Blocked server-side by `RoleMiddleware`) | **HTTP 403 Forbidden**, JSON 403 response | **PASSED** |
| `AUTH-10` | Employee Ownership Verification on Profile (`GET /employee/profile`) | Own profile (`EMP004`) allowed; Tampered ID (`?emp_id=1`) blocked 403 | Own profile: HTTP 200; Tampered ID: HTTP 403 Forbidden | **PASSED** |
| `AUTH-11` | State-Changing Logout & Post-Logout Re-access (`POST /logout`) | HTTP 302 Redirect to `/login`; Session destroyed; Protected routes blocked | Session destroyed; Subsequent requests redirect to `/login` | **PASSED** |

---

## 2. Inactivity Timeout Verification

- **Test Logic:** Injected simulated active session with `last_activity = time() - 1900` (exceeding 1800-second threshold).
- **Execution:** `Core\Session::checkInactivityTimeout()`
- **Result:**
  - `$_SESSION['user']` successfully purged (`UNSET_EXPIRED`).
  - Session ID regenerated.
  - Flash message created: *"Your session has expired due to 30 minutes of inactivity. Please sign in again."*
- **Status:** **PASSED**

---

## 3. Database Audit Logging Verification

Inspection of the `activity_logs` table confirmed that all authentication events were recorded in real-time:
```text
ID  | User ID | Action       | Description                                      | Entity | IP Address | Timestamp
----+---------+--------------+--------------------------------------------------+--------+------------+--------------------
17  | 4       | AUTH_LOGOUT  | User emp004 signed out                           | USER   | ::1        | 2026-10-11 12:08:33
16  | 4       | AUTH_LOGIN   | User emp004 logged in successfully (employee)    | USER   | ::1        | 2026-10-11 12:08:33
15  | 1       | AUTH_LOGIN   | User admin logged in successfully (admin)        | USER   | ::1        | 2026-10-11 12:08:33
14  | 1       | AUTH_FAILED  | Password verification failed for user: admin     | USER   | ::1        | 2026-10-11 12:08:33
```
- Passwords and password hashes were **100% excluded** from all audit logs.
- Client IP addresses and User-Agents were captured cleanly.

---

## 4. Security Findings & Conclusion

All authentication and authorization mechanisms are enforced **server-side**:
1. Frontend tampering (e.g. modifying URL parameters or manually navigating to `/hr/dashboard`) is intercepted by `RoleMiddleware`.
2. Session cookies are protected with `HttpOnly` and `SameSite=Lax`.
3. CSRF protection prevents unauthorized cross-site submissions.
4. Passwords use standard BCRYPT algorithms with transparent rehashing.
