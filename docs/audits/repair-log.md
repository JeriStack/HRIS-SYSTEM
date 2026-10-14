# System Repair & Remediation Log

**Date:** 2026-10-12  
**System:** Employee HRIS & Leave Management System  
**Tracking:** Complete History of Audited Defects, Root Causes, Implemented Fixes, and Verification Evidence

---

## Issue 1: Empty Placeholder Files for In-System Notifications (Phase 10)
- **Problem Statement:** The project requirements (Section 23 of Plan, Module 12) specified an in-system notification center for workflow events. The `notifications` table existed in MySQL with pre-seeded data, but `app/models/Notification.php`, `app/controllers/NotificationController.php`, and `app/views/notifications/index.php` were completely empty (0-byte files), and no routes were registered in `public/index.php`.
- **Root Cause:** Incomplete handoff between database migration and application layer controller/view scaffolding.
- **Files Modified / Created:**
  - `app/models/Notification.php`
  - `app/controllers/NotificationController.php`
  - `app/views/notifications/index.php`
  - `public/index.php`
  - `app/views/partials/sidebar.php`
  - `app/controllers/LeaveController.php`
- **Repair Implemented:**
  1. Built the `Notification` model with `getByUser()`, `getUnreadCount()`, `create()`, `notifyRole()`, `markAsRead()`, `markAllAsRead()`.
  2. Built `NotificationController` with `index()`, `markRead()`, and `markAllRead()`.
  3. Created high-contrast, accessible `notifications/index.php` view with unread badges, filter cards, and CSRF-protected action forms.
  4. Registered `GET /notifications`, `POST /notifications/{id}/read`, and `POST /notifications/mark-all-read` in `public/index.php`.
  5. Added Communications $\rightarrow$ Notifications link in `app/views/partials/sidebar.php`.
  6. Connected `LeaveController` to dispatch notifications to HR/Admin on leave filing, and to employees on leave approval or rejection.
- **Verification Evidence:** `scratch/test_deployment_ui.php` Group 6 passed all 6 test assertions: unauthenticated blocked (302), HR access (200), employee access (200), workflow notification presence, and mark-all-read execution (302).

---

## Issue 2: Incorrect CSRF Token Helper in Notifications View
- **Problem Statement:** Navigating to `GET /notifications` resulted in a Fatal Error / HTTP 500 error: `Call to undefined function csrfField()`.
- **Root Cause:** `app/views/notifications/index.php` called `csrfField()` (camelCase) instead of the defined helper `csrf_field()` (snake_case from `core/functions.php`).
- **Files Modified:**
  - `app/views/notifications/index.php`
- **Repair Implemented:** Replaced all occurrences of `csrfField()` with `csrf_field()`.
- **Verification Evidence:** `GET /notifications` now renders HTTP 200 OK with valid CSRF hidden inputs embedded.

---

## Issue 3: Redundant Undefined Function Calls in NotificationController
- **Problem Statement:** Submitting `POST /notifications/mark-all-read` resulted in HTTP 500 error: `Call to undefined function App\Controllers\validateCsrf()`.
- **Root Cause:** `NotificationController` attempted to manually call non-existent functions `requireAuth()` and `validateCsrf()`, when `AuthMiddleware` and `CsrfMiddleware` already handle these checks at the route layer.
- **Files Modified:**
  - `app/controllers/NotificationController.php`
- **Repair Implemented:** Removed redundant function calls. Rely on route middleware pipeline for authentication and CSRF token validation.
- **Verification Evidence:** `POST /notifications/mark-all-read` successfully returns HTTP 302 Found redirect, marking all alerts as read.

---

## Issue 4: Test Suite Idempotency & Balance Depletion
- **Problem Statement:** Re-running the deployment test suite failed at Step 5 (`Filed request status is 'Pending'`) because `emp004` Vacation Leave balance reached 0 remaining days after multiple test approvals.
- **Root Cause:** Test fixture did not reset test user leave balances before asserting a new leave filing, causing the real business rule to reject the request due to insufficient balance.
- **Files Modified:**
  - `scratch/test_deployment_ui.php`
- **Repair Implemented:** Added fixture reset in test setup (`UPDATE leave_balances SET used_days = 0, remaining_days = allocated_days WHERE employee_id = 4 AND year = 2026`) and attached unique timestamped reason strings.
- **Verification Evidence:** Suite runs cleanly and idempotently across multiple executions (46/46 passed).

---

## Issue 5: Deprecated Function Warning in PHP 8.5
- **Problem Statement:** Deprecation notice for `curl_close()` in PHP 8.5+ cluttered CLI test output.
- **Root Cause:** In PHP 8.5, `curl_close()` has no effect as CurlHandle is automatically garbage-collected.
- **Files Modified:**
  - `scratch/test_deployment_ui.php`
- **Repair Implemented:** Silenced with `@curl_close($ch)`.
- **Verification Evidence:** Clean test execution without deprecation warnings.

---

## Issue 6: Microtime Benchmark Reference Mismatch in DSA Controller
- **Problem Statement:** In `app/controllers/DsaController.php`, benchmark timing referenced `$t0`, `$t1`, and `$t2` inconsistently during sorting runs.
- **Root Cause:** Typographical error in microsecond interval calculation.
- **Files Modified:**
  - `app/controllers/DsaController.php`
- **Repair Implemented:** Corrected benchmark interval calculations: `$quickTime = round(($t1 - $t0) * 1000, 4)` and `$mergeTime = round(($t2 - $t1) * 1000, 4)`.
- **Verification Evidence:** `GET /dsa` renders accurate microsecond benchmarks for QuickSort, MergeSort, and BubbleSort.

---

## Issue 7: Employee Leave Balances and Requests Rendered Raw JSON in Web Browsers
- **Problem Statement:** Navigating to `/employee/leave-balances` and `/employee/leave-requests` in a desktop web browser dumped raw JSON text (`{"status":"success","employee_no":"EMP004",...}`) instead of rendering an enterprise-grade HTML self-service portal view.
- **Root Cause:** `DashboardController::employeeLeaveBalances` and `DashboardController::employeeLeaveRequests` unconditionally invoked `$this->json(...)` without content negotiation checking `Accept: text/html`.
- **Files Modified / Created:**
  - `app/controllers/DashboardController.php`
  - `app/views/dashboard/employee_balances.php`
  - `app/views/dashboard/employee_requests.php`
  - `scratch/test_deployment_ui.php`
- **Repair Implemented:**
  1. Built `app/views/dashboard/employee_balances.php` featuring high-level quota metric cards (Total Allocated, Days Utilized, Days Remaining, Overall Utilization %), detailed balance cards with interactive usage meters, and policy guidance.
  2. Built `app/views/dashboard/employee_requests.php` presenting personal leave filing history with status badges, dates, durations, and pending cancellation actions.
  3. Updated `DashboardController` with content negotiation: Renders HTML view when requested by a browser (`Accept: text/html`), while preserving JSON payload responses for automated API tests.
- **Verification Evidence:** `scratch/test_deployment_ui.php` verified HTML rendering (200 OK) containing "My Leave Balances" and "My Leave Requests". `scratch/test_rbac_suite.php` verified API compatibility with 55/55 passed.

---

## Issue 5: Transition to 2-Role Architecture & Removal of Admin User
- **Problem Statement:** The project requirement was simplified so that organizational users are exclusively **HR Officers** and **Employees** ("Remove the admin user because my users is only HR and employees"). The separate `admin` account and 3rd role was removed in favor of HR acting as the primary workforce and system administration role.
- **Root Cause:** User architectural refinement to a 2-role operational model (Human Resources & Employee Self-Service).
- **Files Modified:**
  - `database/schema.sql` (Altered `role` enum to `ENUM('hr', 'employee')`)
  - `database/seed.sql` (Removed `admin` row, keeping `hr01`, `hr02`, and employee accounts)
  - `core/Permission.php` (Granted user governance and system settings capabilities to `hr`; updated `VALID_ROLES` to `['hr', 'employee']`)
  - `app/controllers/AuthController.php` (Directed HR to `/hr/dashboard` and Employees to `/employee/dashboard`)
  - `app/models/User.php` (Added `countActiveHr()` and lockout protection for active HR administrators)
  - `app/controllers/UserController.php` (Transferred account and role management to HR with last-active HR lockout protection)
  - `public/index.php` (Configured `/hr/users` routes and mapped legacy `/admin` routes)
  - `app/views/auth/login.php` (Updated placeholder to `e.g. hr01 or emp004` and quick login buttons to HR Officer and Employee)
  - `app/views/partials/sidebar.php` (Updated 2-role sidebar: HR Portal and Self-Service Portal)
  - `app/views/dashboard/hr.php` (Added User Accounts & Roles quick shortcut card)
  - `app/views/users/index.php` & `app/views/users/edit.php` (Updated to HR console branding and strict `hr`/`employee` dropdown)
  - `app/views/home/index.php` (Updated landing page mockups, roles grid, and links)
- **Database Executed:**
  - Reassigned 179 activity logs and leave approvals from user 1 (`admin`) to user 2 (`hr01`).
  - Deleted notifications and the `admin` user record from `users`.
  - Executed `ALTER TABLE users MODIFY COLUMN role ENUM('hr', 'employee') NOT NULL DEFAULT 'employee'`.
- **Verification Evidence:**
  - `scratch/test_rbac_suite.php`: 44 Passed, 0 Failed (100%)
  - `scratch/test_deployment_ui.php`: 42 Passed, 0 Failed (100%)
  - `scratch/test_phase7_crud.php`: 51 Passed, 0 Failed (100%)
  - `scratch/test_phase7_http.php`: 34 Passed, 0 Failed (100%)
  - Total automated test suite: 171 / 171 passed (100%).

---

## Verification Summary Across All Suites

```text
========================================================================================
Suite 1: test_rbac_suite.php           -> 44 Passed, 0 Failed (100%)
Suite 2: test_phase7_http.php          -> 34 Passed, 0 Failed (100%)
Suite 3: test_phase7_crud.php          -> 51 Passed, 0 Failed (100%)
Suite 4: test_deployment_ui.php        -> 42 Passed, 0 Failed (100%)
TOTAL AUTOMATED ASSERTIONS VERIFIED   -> 171 Passed, 0 Failed (100%)
========================================================================================
```

## Remaining Concerns & Next Steps
- Production deployment will require remote server provisioning (Apache virtual host pointing to `public/`, TLS Let's Encrypt certificate, and cloud environment variables).
- All application-level features, security policies, and 2-role user workflows are verified and operational.
