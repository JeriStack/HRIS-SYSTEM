# RBAC & Server-Side Authorization Security Test Report
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Test Execution Date:** 2026-10-12  
**Environment:** Local XAMPP (Apache, PHP 8.5.9, MariaDB 10.4 / MySQL 8.x, PDO)  
**Test Suite Scripts:**  
- `scratch/test_rbac_suite.php` (Master RBAC Suite — 55 tests)  
- `scratch/test_phase7_http.php` (Core HRIS HTTP Suite — 34 tests)  
- `scratch/test_phase7_crud.php` (Core HRIS Model/Validation Suite — 51 tests)  
**Total Executed Tests:** 140  
**Overall Result:** **140 PASSED / 0 FAILED (100% Pass Rate)**

---

## 1. Test Execution Summary

| Test Domain | Test Count | Passed | Failed | Status |
| :--- | :---: | :---: | :---: | :---: |
| **Authentication & Credentials** | 12 | 12 | 0 | **PASS** |
| **Mutually Exclusive Dashboards** | 12 | 12 | 0 | **PASS** |
| **Admin User & Role Governance** | 13 | 13 | 0 | **PASS** |
| **Employee Resource Ownership** | 8 | 8 | 0 | **PASS** |
| **Session & Logout Security** | 10 | 10 | 0 | **PASS** |
| **Core HRIS CRUD & Route Guards** | 85 | 85 | 0 | **PASS** |
| **Total Test Assertions** | **140** | **140** | **0** | **100% PASS** |

---

## 2. Detailed Test Category Results

### A. Authentication & Session Verification
- `[PASS]` Login page renders successfully with HTTP 200.
- `[PASS]` CSRF token is present on login form.
- `[PASS]` Submitting invalid password redirects to `/login` (HTTP 302) with generic error banner.
- `[PASS]` Submitting unknown username redirects to `/login` (HTTP 302) without leaking account existence.
- `[PASS]` Submitting empty credentials is rejected immediately.
- `[PASS]` Submitting login POST without valid CSRF token is rejected.
- `[PASS]` Administrator (`admin` / `Admin@123`) authenticates successfully and dispatches to `/admin/dashboard`.
- `[PASS]` HR Officer (`hr01` / `Admin@123`) authenticates successfully and dispatches to `/hr/dashboard`.
- `[PASS]` Employee (`emp004` / `Employee@123`) authenticates successfully and dispatches to `/employee/dashboard`.
- `[PASS]` Session regeneration (`Session::regenerate(true)`) occurs after successful login.
- `[PASS]` POST `/logout` with valid CSRF destroys session and invalidates cookies.
- `[PASS]` Post-logout requests to protected resources redirect to `/login` (HTTP 302).

### B. Mutually Exclusive Dashboard Isolation Matrix
| Authenticated Identity | Target Route | Expected Code | Actual Code | Result |
| :--- | :--- | :---: | :---: | :---: |
| **Unauthenticated** | `/admin/dashboard` | 302 (Redirect) | 302 | **PASS** |
| **Unauthenticated** | `/hr/dashboard` | 302 (Redirect) | 302 | **PASS** |
| **Unauthenticated** | `/employee/dashboard` | 302 (Redirect) | 302 | **PASS** |
| **Administrator** (`admin`) | `/admin/dashboard` | 200 OK | 200 | **PASS** |
| **Administrator** (`admin`) | `/hr/dashboard` | 403 Forbidden | 403 | **PASS** |
| **Administrator** (`admin`) | `/employee/dashboard` | 403 Forbidden | 403 | **PASS** |
| **HR Officer** (`hr`) | `/admin/dashboard` | 403 Forbidden | 403 | **PASS** |
| **HR Officer** (`hr`) | `/hr/dashboard` | 200 OK | 200 | **PASS** |
| **HR Officer** (`hr`) | `/employee/dashboard` | 403 Forbidden | 403 | **PASS** |
| **Employee** (`employee`) | `/admin/dashboard` | 403 Forbidden | 403 | **PASS** |
| **Employee** (`employee`) | `/hr/dashboard` | 403 Forbidden | 403 | **PASS** |
| **Employee** (`employee`) | `/employee/dashboard` | 200 OK | 200 | **PASS** |

### C. Privileged User & Role Governance
- `[PASS]` HR Officer denied entry to `/admin/users` (HTTP 403 Forbidden).
- `[PASS]` Employee denied entry to `/admin/users` (HTTP 403 Forbidden).
- `[PASS]` Administrator accesses `/admin/users` (HTTP 200 OK) with live database user list.
- `[PASS]` HR Officer denied entry to `/admin/users/{id}/edit` (HTTP 403 Forbidden).
- `[PASS]` Employee denied entry to `/admin/users/{id}/edit` (HTTP 403 Forbidden).
- `[PASS]` Administrator views user edit form with CSRF protection (HTTP 200 OK).
- `[PASS]` User update POST without CSRF token is rejected.
- `[PASS]` Updating user with invalid role (`superadmin`) is rejected by allowlist validation.
- `[PASS]` Administrator updates user role (`hr02` role and status) successfully with audit log entry.
- `[PASS]` Administrator attempting self-demotion is rejected to prevent self-lockout.
- `[PASS]` Administrator attempting self-deactivation is rejected to prevent self-lockout.
- `[PASS]` Deactivating or demoting the last active administrator is blocked by system lockout protection.

### D. Employee Record Ownership & IDOR Protection
- `[PASS]` Employee EMP004 views own personal profile at `/employee/profile` (HTTP 200 OK).
- `[PASS]` Employee EMP004 attempting query tampering `?emp_id=5` receives HTTP 403 Forbidden.
- `[PASS]` Employee EMP004 views own leave balances API at `/employee/leave-balances` (HTTP 200 OK).
- `[PASS]` Balances API parameter tampering `?emp_id=5` receives HTTP 403 Forbidden.
- `[PASS]` Employee EMP004 views own leave requests API at `/employee/leave-requests` (HTTP 200 OK).
- `[PASS]` Requests API parameter tampering `?emp_id=5` receives HTTP 403 Forbidden.

---

## 3. Security Assessment

The implementation strictly satisfies all non-functional security requirements:
- **NFR-SEC-001 (Password Encryption):** Validated via `password_hash()` and `password_verify()`.
- **NFR-SEC-002 (Role-Based Authorization):** Centralized `Core\Permission` + `RoleMiddleware` enforced across all routes.
- **NFR-SEC-003 (CSRF Protection):** Enforced on all state-changing `POST` requests.
- **NFR-SEC-004 (Data Ownership):** Enforced server-side against URL parameter spoofing.
- **NFR-SEC-005 (SQL Injection Prevention):** Parameterized PDO prepared statements used exclusively.
- **NFR-SEC-006 (XSS Mitigation):** Contextual `e()` escaping applied on all user-supplied views.
