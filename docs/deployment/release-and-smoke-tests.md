# Release Checklist & Smoke Tests Guide

## Employee HRIS & Leave Management System

### 1. Pre-Deployment Release Checklist

Before promoting any release to production, verify each item:

- [ ] **No Uncommitted Code:** Working directory is clean; release commit is tagged (`git tag v1.0.0`).
- [ ] **Secrets Excluded:** `.env` is listed in `.gitignore` and has not been committed.
- [ ] **Configuration Mode:** `APP_ENV=production` is set in production `.env`.
- [ ] **DocumentRoot Isolated:** Web server serves strictly from `public/`.
- [ ] **Directory Protection Verified:** `.htaccess` with `Require all denied` present in `app/`, `core/`, `config/`, `database/`, and `scratch/`.
- [ ] **Database Pre-Migration Backup:** Full SQL dump created and verified.
- [ ] **Database Connection Checked:** Dedicated application database user tested with least privileges.
- [ ] **HTTPS Enabled:** SSL certificate installed and HTTP strictly redirects to HTTPS.
- [ ] **Error Reporting Hardened:** `display_errors` disabled; `log_errors` enabled to write to `/var/log/apache2/error.log`.

---

### 2. Automated Smoke Test Execution

Execute the three comprehensive test suites directly via CLI:

#### Suite 1: RBAC & Server-Side Authorization Suite (55 Tests)
```bash
php scratch/test_rbac_suite.php
```
**Expected Result:** 55/55 Tests Passed (100% Pass Rate).
Validates:
- Authentication, CSRF guards, and password validation.
- Mutually exclusive dashboard isolation matrix (Admin, HR, Employee).
- Admin user governance, lockout protection, and self-demotion blocks.
- Employee profile and leave balance IDOR ownership enforcement (`?emp_id=5` -> HTTP 403).
- Session invalidation on `POST /logout`.

#### Suite 2: Core HRIS CRUD Operations Suite (51 Tests)
```bash
php scratch/test_phase7_crud.php
```
**Expected Result:** 51/51 Tests Passed (100% Pass Rate).
Validates:
- Department model uniqueness, creation, and lookups.
- Position model uniqueness and department associations.
- Employee creation, duplicate prevention, and soft-deactivation integrity.
- Server-side validation rules (dates, email format, status).

#### Suite 3: Live HTTP End-to-End Integration Suite (34 Tests)
```bash
php scratch/test_phase7_http.php
```
**Expected Result:** 34/34 Tests Passed (100% Pass Rate).
Validates:
- Unauthenticated access redirects to login (HTTP 302).
- Employee role blocked from HR routes (HTTP 403).
- Full live HTTP lifecycle: Create employee -> View profile -> Edit employee -> Deactivate -> Search.
- Audit log records populated for each state change.

---

### 3. Manual Workflow Validation Matrix

#### Admin Journey
1. Sign in as `admin`.
2. Access `/admin/dashboard` &rarr; verify `ADMINISTRATOR CONSOLE` badge and system overview metrics.
3. Access `/admin/users` &rarr; verify user accounts list.
4. Attempt to access `/hr/dashboard` or `/employee/dashboard` &rarr; confirm HTTP 403 Forbidden.

#### HR Journey
1. Sign in as `hr01`.
2. Access `/hr/dashboard` &rarr; verify `HR OPERATIONAL PORTAL` banner and operational headcount.
3. Access `/hr/employees` &rarr; test search and department filter.
4. Access `/leaves` &rarr; review pending leave requests, approve or reject a request.
5. Attempt to access `/admin/users` or `/admin/dashboard` &rarr; confirm HTTP 403 Forbidden.

#### Employee Journey
1. Sign in as `emp004`.
2. Access `/employee/dashboard` &rarr; verify `EMPLOYEE SELF-SERVICE` banner and personal leave balances.
3. Access `/leaves/apply` &rarr; verify working days counter (Mon-Fri) and submit request.
4. Access `/employee/profile` &rarr; view own record.
5. Attempt tampering with `?emp_id=5` &rarr; confirm HTTP 403 Forbidden with Ownership Access Denied.
6. Attempt to access `/hr/employees` or `/admin/users` &rarr; confirm HTTP 403 Forbidden.

---

### 4. Post-Deployment Verification Commands
Run these quick curl checks against the live production URL:
```bash
# 1. Verify Public Homepage
curl -I -s https://hris.example.com/ | grep "HTTP/"

# 2. Verify Directory Protection (Should return 403)
curl -I -s https://hris.example.com/app/ | grep "HTTP/"
curl -I -s https://hris.example.com/config/config.php | grep "HTTP/"

# 3. Verify Security Headers
curl -I -s https://hris.example.com/ | grep -E "X-Frame-Options|X-Content-Type-Options|Referrer-Policy"
```
