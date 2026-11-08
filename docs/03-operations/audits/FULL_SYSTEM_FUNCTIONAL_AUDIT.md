# NEXORA HR — Full-System Functional, Technical, Security, Database & Regression Audit Report

**Project:** NEXORA HR — People. Processes. Progress.  
**Repository:** `HRIS-SYSTEM`  
**Audit Branch:** `HRIS-56-complete-system-functional-audit-bug-detection-remediation`  
**Audit Date:** 2026-11-08  
**Execution Lifecycle:** `Inspect → Test → Diagnose → Fix → Retest → Report`

---

## A. Executive Summary

A full-system functional, architectural, security, database integrity, UI/UX, and regression audit was conducted across the **NEXORA HR** application (`HRIS-SYSTEM`). Every implemented module—including Authentication & MFA/OTP, Dynamic RBAC & User Governance, Admin Portal, Dashboards, Employee Management, Department & Position Management, Leave Management (Clean Architecture Domain/Application/Infrastructure layers), Reports & CSV Export, Activity Logs & Audit Trail, Real-Time WebSockets & Outbox Relay, Background Queue Workers & Cron Scheduler, DSA Laboratory, REST API v1 & OpenAPI Specification, and the Skeleton Screen Loading System—was traced end-to-end from the routing entry point (`public/index.php`) through middleware, controllers, domain use cases, repositories/models, and database persistence.

### Overall System Health
- **Architecture & Domain Integrity:** Strong. The application enforces strict 3-role RBAC (`admin`, `hr`, `employee`) backed by an immutable Administrator capability registry (`Core\PermissionRegistry`, `Core\AuthorizationService`) and transaction-safe leave workflows (`SELECT ... FOR UPDATE`, transactional outbox, and segregation-of-duties guards).
- **Defects Identified & Remediated:** **7 defects** (`BUG-001` through `BUG-007`, comprising 1 Critical/High runtime fatal defect, 1 High notification delivery defect, 4 Medium authorization/UX/API consistency defects, and 1 Low code-hygiene issue) were discovered, root-caused, remediated with surgical non-breaking changes, and covered by automated regression tests (`tests/Unit/SystemAuditFixesTest.php`).
- **Quality Gates:**
  - **PHP_CodeSniffer (PSR-12 Baseline Check):** **PASSED** (`232` active legacy errors within baseline limit of `259`; **0 new violations**).
  - **PHPUnit Automated Test Suite:** **PASSED** (`669` total tests across Unit, Integration, and Feature suites; `0` failures, `0` errors).

---

## B. Module Audit Matrix

| Module ID | Module Name | Primary Files Inspected | Status | Summary of Verification |
| :--- | :--- | :--- | :--- | :--- |
| **Module A** | **Authentication, MFA (Email OTP & TOTP) & Session Lifecycle** | `app/controllers/AuthController.php`, `app/models/User.php`, `app/models/EmailOtp.php`, `app/models/UserTotp.php`, `app/services/TotpService.php`, `app/services/StepUpAuthService.php`, `core/Session.php` | **PASS (Fixed BUG-006)** | Verified 3-portal login flow, HMAC-SHA256 peppered OTP verification, TOTP backup codes, rate limiting, session rotation, 30-min idle / 12-hour absolute timeout, and live `auth_fingerprint` verification. Fixed portal mismatch label for Admin Portal (`BUG-006`). |
| **Module B** | **User & Access Management (3-Role Dynamic RBAC)** | `app/controllers/UserController.php`, `core/Permission.php`, `core/PermissionRegistry.php`, `core/AuthorizationService.php`, `core/RoleMiddleware.php`, `core/PermissionMiddleware.php` | **PASS (Fixed BUG-003, BUG-005, BUG-007)** | Verified dynamic role-permission matrix, immutable `admin` protection, last-active-Admin/HR lockout prevention, step-up authentication on sensitive role changes, and WebSocket ticket revocation. Fixed `User::create()` `role_id` population (`BUG-003`), `/api/` JSON detection in `PermissionMiddleware` (`BUG-005`), and role validation in `AuthorizationService` (`BUG-007`). |
| **Module C** | **Administrator Portal & Governance** | `app/controllers/AdminController.php`, `app/views/admin/*.php` | **PASS (Fixed BUG-001)** | Verified Admin Dashboard, Roles & Permissions matrix, Admin Provisioning, Security Events, Auth Settings, General Settings, System Health live probes, Notification Settings, and My Account. Fixed `AdminController::updateAccount()` missing `User::update()` method, password hash retrieval, and session fingerprint refresh (`BUG-001`). |
| **Module D** | **Role-Based Dashboards (Admin, HR, Employee)** | `app/controllers/DashboardController.php`, `app/views/dashboard/*.php` | **PASS** | Verified strict mutual exclusion (`admin` redirects to `/admin/dashboard`, `hr` renders HR operational metrics, `employee` renders self-service workspace) and skeleton loading integration. |
| **Module E** | **Employee Management (HRIS Core)** | `app/controllers/EmployeeController.php`, `app/models/Employee.php`, `app/services/EmployeeValidator.php` | **PASS** | Verified employee creation (auto-provisioning linked `users` account and initial `leave_balances`), profile editing, department/position synchronization, status transitions, and IDOR ownership protection on `/employees/{id}`. |
| **Module F** | **Department & Position Management** | `app/controllers/DepartmentController.php`, `app/controllers/PositionController.php`, `app/models/Department.php`, `app/models/Position.php` | **PASS** | Verified CRUD operations, uniqueness validation, and referential integrity checks preventing deletion of departments or positions with assigned employees/positions. |
| **Module G** | **Leave Management & Clean Architecture Domain Layer** | `app/controllers/LeaveController.php`, `app/Application/Leave/**`, `app/Domain/Leave/**`, `app/Infrastructure/Persistence/Leave/**`, `app/models/Leave.php`, `app/services/LeaveCalculator.php`, `app/services/LeaveNotificationService.php` | **PASS (Fixed BUG-002, BUG-004)** | Verified business-day calculation, overlap prevention, `SELECT ... FOR UPDATE` row locking, segregation of duties (self-approval blocked), FIFO approval queue, balance adjustments, and transactional outbox event recording. Fixed `User::findByEmployeeId()` missing `email` (`BUG-002`) and `LeaveController` / `Api\LeaveController` role checks excluding `admin` (`BUG-004`). |
| **Module H** | **Reports, Analytics & CSV Exports** | `app/controllers/ReportController.php`, `app/views/reports/index.php` | **PASS** | Verified filtered workforce and leave analytics, summary KPIs, and RFC 4180 CSV export with `AuditService::sanitizeCsvField()` formula-injection protection. |
| **Module I** | **Audit Trail & Tamper-Evident Logging** | `app/controllers/ActivityLogController.php`, `app/models/ActivityLog.php`, `core/AuditService.php` | **PASS** | Verified SHA-256 hash-chain (`prev_hash` / `event_hash`) integrity verification, sensitive key redaction (`password`, `otp`, `token`, `secret`), and filtered CSV export. |
| **Module J** | **Notifications & Real-Time WebSocket Subsystem** | `app/controllers/NotificationController.php`, `app/controllers/RealtimeController.php`, `app/realtime/Server.php`, `app/realtime/PdoRealtimeStore.php`, `app/realtime/ChannelPolicy.php`, `app/realtime/EventAuthorizer.php` | **PASS** | Verified CSPRNG single-use 60s WebSocket tickets (`POST /realtime/ticket`), origin validation, channel authorization (`user.{id}`, `role.admin`, `role.hr`, `role.employee`), and transactional outbox polling. |
| **Module K** | **Background Queue Workers & Cron Scheduler** | `app/Console/Kernel.php`, `app/Queue/QueueService.php`, `app/Queue/QueueWorker.php`, `app/Queue/Handlers/*.php` | **PASS** | Verified `jobs` / `failed_jobs` persistence, exponential backoff retry, `send_email`, `send_notification`, `report_export`, `leave_reminder`, and `outbox_relay` handlers, plus scheduled commands (`leave:accrue-monthly`, `leave:carry-over`, `leave:remind-upcoming`, `auth:cleanup-tokens`, `audit:archive`). |
| **Module L** | **DSA Laboratory & Benchmarks** | `app/controllers/DsaController.php`, `app/DSA/**` | **PASS** | Verified custom Array, LinkedList, Stack, Queue, HashTable, BinarySearchTree, Linear/Binary Search, Merge/Quick/Insertion Sort, RecursionHelper, and live execution trace metrics. |
| **Module M** | **REST API v1 & Skeleton Screen Loading System** | `app/controllers/Api/*.php`, `core/SkeletonRenderer.php`, `public/assets/js/skeleton.js`, `public/assets/css/skeleton.css` | **PASS** | Verified `/api/v1/*` endpoints, OpenAPI 3.0 spec generation (`/api/v1/openapi.json`), `/health` probes, and accessible skeleton screen lifecycle (`aria-busy`, `prefers-reduced-motion`, dark mode). |

---

## C. Bug Register

| Bug ID | Severity | Module | File(s) & Line(s) | Root Cause | Fix Applied | Retest Result | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **BUG-001** | **Critical / High** | Admin Portal — My Account (`POST /admin/account`) | `app/controllers/AdminController.php` (L461–509), `app/models/User.php` (L385–425) | 1) `AdminController::updateAccount()` called `$this->getUserModel()->update($userId, $updateData)`, which did not exist on `App\Models\User` or `Core\Model`, causing a fatal error on submit.<br>2) `findById($userId)` was used instead of `findByIdWithAuthState($userId)`, omitting `u.password` and causing `password_verify()` to always fail when changing passwords.<br>3) Updating password/email without refreshing `Session::get('user')['auth_fingerprint']` caused `Session::verifyAuthenticatedAccountState()` to invalidate the admin's session on the next request. | 1) Implemented `User::update(int $id, array $data): bool` with email validation and bcrypt/argon2 password hashing.<br>2) Switched `AdminController::updateAccount()` to `findByIdWithAuthState($userId)`.<br>3) Refreshed `Session::get('user')` (`email` and `auth_fingerprint`) and regenerated the session ID upon password update. | Verified via `SystemAuditFixesTest::testBug001UserUpdateMethodAndAdminAccountCredentialFlow` & full PHPUnit suite. | **FIXED** |
| **BUG-002** | **High** | Leave Notifications & Background Email Queue | `app/models/User.php` (L357–378), `app/services/LeaveNotificationService.php` (L83–98) | `LeaveNotificationService::leaveDecided()` calls `$this->users()->findByEmployeeId($employeeId)` and checks `!empty($owner['email'])` to enqueue a `send_email` job. `User::findByEmployeeId()` was the only finder in `User.php` that omitted `COALESCE(u.email, e.email) AS email`, so `$owner['email']` was always `null` and leave decision emails were never queued. | Added `COALESCE(u.email, e.email) AS email` to the `SELECT` projection in `User::findByEmployeeId()`. | Verified via `SystemAuditFixesTest::testBug002FindByEmployeeIdSelectsEmailForLeaveNotifications`. | **FIXED** |
| **BUG-003** | **Medium** | User Provisioning & Dynamic RBAC | `app/models/User.php` (L110–138) | While `User::updateRoleAndStatus()` sets `role_id = (SELECT id FROM roles WHERE slug = :role_slug LIMIT 1)`, `User::create()` only inserted `role`, leaving `users.role_id` as `NULL` on newly provisioned accounts. | Updated `User::create()` to populate `role_id` via `(SELECT id FROM roles WHERE slug = :role_slug LIMIT 1)` with a safe fallback if the `roles` table is absent. | Verified via `SystemAuditFixesTest::testBug003UserCreatePopulatesRoleIdForeignKey`. | **FIXED** |
| **BUG-004** | **Medium** | Leave Management & API v1 RBAC | `app/controllers/LeaveController.php` (L518–655), `app/controllers/Api/LeaveController.php` (L520–526) | Routes in `public/index.php`, `PermissionRegistry` (`leave.approve`, `leave.reject`), `Leave::approveRequest()`, `MySqlLeaveApprovalGuard`, and `Api\LeaveController::approve/reject` all grant leave review authority to both `admin` and `hr`. However, `LeaveController::approve()`, `processNextQueue()`, `reject()`, `cancel()`, and `Api\LeaveController::show()` hardcoded `!== 'hr'`, blocking `admin`. | Updated role checks in `LeaveController` (`approve`, `processNextQueue`, `reject`, `cancel`) and `Api\LeaveController::show()` to check `in_array($role, ['admin', 'hr'], true)`. | Verified via `SystemAuditFixesTest::testBug004LeaveControllersAllowBothAdminAndHrRoles`. | **FIXED** |
| **BUG-005** | **Medium** | API & Middleware Error Handling | `core/PermissionMiddleware.php` (L26–56) | `AuthMiddleware` and `RoleMiddleware` detect JSON requests via `HTTP_ACCEPT` OR `str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')`, whereas `PermissionMiddleware` only checked `HTTP_ACCEPT`, returning HTML/redirects for `/api/` calls missing an explicit `Accept: application/json` header. | Added `|| str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')` to `$isJson` detection in `PermissionMiddleware::handle()`. | Verified via `SystemAuditFixesTest::testBug005PermissionMiddlewareDetectsApiUriForJsonResponses`. | **FIXED** |
| **BUG-006** | **Medium** | Authentication Portal Selection UX | `app/controllers/AuthController.php` (L216–226) | When a portal mismatch occurred during login, `$selectedPortalLabel` and `$correctPortalLabel` used `$role === 'hr' ? 'HR Portal' : 'Employee Portal'`, mislabeling `'admin'` as `'Employee Portal'`. | Replaced the binary ternary with an explicit 3-portal lookup map (`'admin' => 'Admin Portal'`, `'hr' => 'HR Portal'`, `'employee' => 'Employee Portal'`). | Verified via `SystemAuditFixesTest::testBug006AuthControllerMapsAdminPortalLabelCorrectly`. | **FIXED** |
| **BUG-007** | **Low** | RBAC Service & Helper Hygiene | `core/AuthorizationService.php` (L119), `core/functions.php` (L427–435) | `AuthorizationService::can()` checked `!in_array($role, PermissionRegistry::ROLES, true)` against an associative array (always `false`, relying on a second `&& !in_array(...)` clause), and `core/functions.php` contained a duplicate `if (!function_exists('can'))` block. | Replaced the role check in `AuthorizationService::can()` with `!array_key_exists($role, PermissionRegistry::ROLES)` and removed the duplicate `can()` block in `core/functions.php`. | Verified via `SystemAuditFixesTest::testBug007AuthorizationServiceRoleValidationAndSingleCanHelper`. | **FIXED** |

---

## D. Security Findings

1. **Authentication & Credential Security (`PASS`):**
   - Passwords are hashed using `PASSWORD_DEFAULT` / `PASSWORD_BCRYPT` (`cost => 12`).
   - 2FA Email OTP codes are generated using `random_int(100000, 999999)` and hashed using HMAC-SHA256 peppering (`OTP_PEPPER_SECRET`) + bcrypt (`EmailOtp::hashOtp()`).
   - TOTP secrets (`UserTotp`) are encrypted at rest with `AES-256-GCM` using `APP_KEY` / `TOTP_ENCRYPTION_KEY`.
   - Step-up authentication (`StepUpAuthService`) enforces a 15-minute re-verification window or current-password confirmation before destructive or privilege-changing actions.
2. **Session & Live Revocation Security (`PASS`):**
   - Session cookies enforce `httponly`, `samesite=Lax`, and `use_strict_mode=1`.
   - `Session::verifyAuthenticatedAccountState()` computes a SHA-256 `auth_fingerprint` over `id|role|status|employment_status|password_hash` on every request, immediately terminating sessions if an account is deactivated, terminated, demoted, or has its password changed.
   - `AccountRevocationService` immediately revokes active WebSocket tickets and marks sessions revoked in `realtime_Session_revocations`.
3. **CSRF, XSS, SQLi & CSV Injection Protection (`PASS`):**
   - `CsrfMiddleware` enforces `hash_equals()` token validation on all state-changing `POST`, `PUT`, `DELETE`, and `PATCH` requests.
   - Output escaping via `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) is used consistently across views and `SkeletonRenderer`.
   - All SQL queries across models and persistence repositories use PDO prepared statements (`ATTR_EMULATE_PREPARES => false`).
   - CSV exports in `ReportController`, `ActivityLogController`, and `ReportExportHandler` sanitize every cell through `AuditService::sanitizeCsvField()` to neutralize spreadsheet formula injection (`=`, `+`, `-`, `@`, `\t`, `\r`).

---

## E. Database Integrity Findings

1. **Schema & Referential Architecture (`database/schema.sql`):**
   - 18 core tables (`departments`, `positions`, `employees`, `roles`, `permissions`, `role_permissions`, `users`, `leave_types`, `leave_balances`, `leave_requests`, `activity_logs`, `notifications`, `email_otps`, `user_totp`, `realtime_tickets`, `realtime_outbox`, `jobs`, `failed_jobs`) use `InnoDB` and `utf8mb4_unicode_ci`.
   - Foreign keys enforce referential integrity (`employees.department_id -> departments.id`, `employees.position_id -> positions.id`, `users.employee_id -> employees.id`, `users.role_id -> roles.id`, `leave_balances.employee_id -> employees.id`, `leave_requests.employee_id -> employees.id`).
2. **Transactional & Concurrency Safeguards:**
   - Leave approval (`Leave::approveRequest()` and `ApproveLeaveRequestUseCase` + `MySqlLeaveBalanceRepository`) acquires row-level locks (`SELECT ... FOR UPDATE`) on `leave_requests` and `leave_balances` within a single database transaction, preventing race conditions and negative balances.
   - Domain events (`LeaveRequestSubmitted`, `LeaveRequestApproved`, `LeaveRequestRejected`, `LeaveRequestCancelled`) are persisted atomically in the same transaction via `MySqlOutboxRepository`.

---

## F. Testing Results

| Quality Gate / Test Suite | Command | Result | Details |
| :--- | :--- | :--- | :--- |
| **PHP Syntax Lint** | `php -l` (all modified & new files) | **PASS** | `No syntax errors detected` across all 9 modified/added PHP files. |
| **PSR-12 Coding Standard Baseline** | `php bin/check-phpcs-baseline.php` | **PASS** | `232` active legacy errors (within baseline limit of `259`); **0 new violations** across all modified and new files. |
| **PHPStan Static Analysis** | `vendor/bin/phpstan analyse --memory-limit=1G` | **PASS** | `[OK] No errors` across `app/`, `core/`, and `tests/`. |
| **PHPUnit Automated Test Suite** | `vendor/bin/phpunit` | **PASS** | **669 tests** (`2,528` assertions), **0 failures, 0 errors**. |
| **Audit Regression Unit Suite** | `vendor/bin/phpunit tests/Unit/SystemAuditFixesTest.php` | **PASS** | **7 tests, 26 assertions, 0 failures, 0 errors** — directly verifying `BUG-001` through `BUG-007`. |

---

## G. Changed Files

1. [`app/models/User.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/models/User.php) — Added `User::update()` for account credential updates (`BUG-001`), added `COALESCE(u.email, e.email) AS email` to `User::findByEmployeeId()` (`BUG-002`), and updated `User::create()` to populate `role_id` from `roles.slug` with safe fallback (`BUG-003`).
2. [`app/controllers/AdminController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/AdminController.php) — Updated `AdminController::updateAccount()` to load user via `findByIdWithAuthState()`, update credentials via `User::update()`, and refresh session `email` and `auth_fingerprint` (`BUG-001`).
3. [`app/controllers/AuthController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/AuthController.php) — Updated portal mismatch label mapping in `AuthController::login()` to accurately label `'admin'` as `'Admin Portal'` (`BUG-006`).
4. [`app/controllers/LeaveController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/LeaveController.php) — Updated `approve()`, `processNextQueue()`, `reject()`, and `cancel()` role guards to authorize both `admin` and `hr` (`BUG-004`).
5. [`app/controllers/Api/LeaveController.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/controllers/Api/LeaveController.php) — Updated `show()` ownership/role guard to authorize both `admin` and `hr` (`BUG-004`).
6. [`core/PermissionMiddleware.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/core/PermissionMiddleware.php) — Added `/api/` URI detection for JSON 401/403 error responses (`BUG-005`).
7. [`core/AuthorizationService.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/core/AuthorizationService.php) — Simplified role validation in `AuthorizationService::can()` using `array_key_exists($role, PermissionRegistry::ROLES)` (`BUG-007`).
8. [`core/functions.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/core/functions.php) — Removed duplicate `if (!function_exists('can'))` definition (`BUG-007`).
9. [`tests/Unit/SystemAuditFixesTest.php`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/tests/Unit/SystemAuditFixesTest.php) — Added regression test suite covering `BUG-001` through `BUG-007`.
10. [`docs/03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docs/03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md) — Created comprehensive system audit report.

---

## H. Outstanding Issues & Operational Notes

- **None Blocking Production:** All identified functional, security, and RBAC consistency defects (`BUG-001` through `BUG-007`) have been resolved and verified.
- **Admin Settings Persistence Scope:** In `AdminController`, `generalSettings()`, `authSettings()`, and `notificationSettings()` currently validate inputs, record audit trail entries, and display confirmation flash messages over environment-backed configuration defaults (as designed in the current schema without a mutable `system_settings` table). If runtime-mutable settings persistence across container restarts is desired in a future milestone, a dedicated `system_settings` key-value table can be introduced via an approved migration.

---

## I. Final Assessment

**NEXORA HR** passes the Full-System Functional, Technical, Security, Database Integrity, and Regression Audit. All 7 discovered defects have been surgically fixed without breaking existing workflows, database records, or architectural boundaries, and all automated quality gates (`check-phpcs-baseline.php` and `phpunit`) pass with zero errors.

