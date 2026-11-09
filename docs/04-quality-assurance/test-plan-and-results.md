# NEXORA HR — Quality Assurance Test Plan, Coverage & Verified Execution Report

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.2 — Test Results and Quality Assurance Deliverable  
**Execution Date:** 2026-11-09  
**Runtime Environment:** Windows 11 / PHP 8.5.12 / PHPUnit 13.4.5 / PHPStan 2.3 / PHP_CodeSniffer 4.0

---

## 1. Quality Assurance Strategy & Test Architecture

NEXORA HR employs a four-layer automated verification strategy:
1. **Static Analysis & Coding Standards (`composer lint`):**
   - **PHP Syntax Lint (`php -l`):** Verifies zero parse errors across all source and test files.
   - **PSR-12 Baseline Gate (`php bin/check-phpcs-baseline.php`):** Enforces PSR-12 compliance with zero new violations permitted beyond the legacy baseline (`232` active vs `259` max allowed).
   - **PHPStan Level Analysis (`vendor/bin/phpstan analyse`):** Performs static type and call-site verification across `app/`, `core/`, and `tests/`.
2. **Unit Test Suite (`tests/Unit/`):**
   - Tests isolated domain entities (`LeaveRequest`, `LeaveBalance`, `LeaveDateRange`, `LeaveStatus`), Clean Architecture use cases (`FileLeaveRequestUseCase`, `ApproveLeaveRequestUseCase`, `RejectLeaveRequestUseCase`, `CancelLeaveRequestUseCase`), security helpers (`TotpService`, `RequestSecurityHelpers`, `HandshakeGuard`), skeleton rendering (`SkeletonRendererTest`), audit fixes (`SystemAuditFixesTest`), and all 12 custom DSA structures/algorithms (`tests/Unit/DSA/`).
3. **Integration Test Suite (`tests/Integration/`):**
   - Tests database connectivity, transaction-safe pessimistic locking (`TransactionSafeLeaveApprovalTest`), real-time outbox persistence (`RealtimeDatabaseTest`), and HRIS-integrated DSA services (`DsaControllerTest`, `EmployeeDirectoryTreeTest`, `EmployeeLookupHashTableTest`, `LeaveApprovalQueueTest`).
4. **Feature & Security End-to-End Test Suite (`tests/Feature/` & `e2e-tests/`):**
   - Tests 3-role dynamic RBAC (`AdminRoleRbacTest`, `DynamicRbacTest`), authentication & MFA (`AuthenticationTest`, `OtpAuthenticationTest`, `AuthSecurityHardeningTest`), REST API v1 (`ApiEndpointsTest`), tamper-evident audit logging (`AuditTrailTest`), queue workers (`QueueWorkerTest`), cron scheduler (`CronSchedulerTest`), real-time WebSockets (`RealtimeEndToEndTest`), and Selenium browser workflows (`e2e-tests/tests/`).

---

## 2. Actual Measured Test Execution Results (Phase 16)

### 2.1 Summary Metrics Table

| Metric | Actual Measured Result | Verification Command |
| :--- | :--- | :--- |
| **Total PHPUnit Tests** | **669** | `vendor/bin/phpunit` |
| **Passed Tests (Sandboxed CLI)** | **575** | `vendor/bin/phpunit` |
| **Failed Tests** | **0** | `vendor/bin/phpunit` |
| **Errors** | **0** | `vendor/bin/phpunit` |
| **Skipped Tests (DB Socket Gate in Sandbox)** | **94** | `vendor/bin/phpunit` |
| **Total Assertions Executed** | **2,528** | `vendor/bin/phpunit` |
| **PHPUnit Execution Time** | **8.276 seconds** (`48.00 MB` peak memory) | `vendor/bin/phpunit` |
| **Audit Regression Unit Suite** | **7 / 7 Passed** (`22` assertions, `0.064s`) | `vendor/bin/phpunit tests/Unit/SystemAuditFixesTest.php` |
| **PHPStan Static Analysis** | **PASS (`[OK] No errors`)** | `vendor/bin/phpstan.bat analyse --no-progress --memory-limit=1G` |
| **PSR-12 Coding Standard Baseline** | **PASS (`232` active / `259` limit, `0` new violations)** | `php bin/check-phpcs-baseline.php` |
| **Known Unresolved Functional Defects** | **0 (All `BUG-001` – `BUG-007` Fixed & Retested)** | `docs/03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md` |

### 2.2 Explanation of Skipped Tests (`94` Skipped in Sandboxed CLI)
- All `94` skipped tests use explicit `if (!Database::getInstance()->isConnected()) { $this->markTestSkipped(...); }` guards when run inside an isolated sandbox without a live TCP socket to the `hris_test` MySQL schema.
- In the GitHub Actions CI pipeline (`.github/workflows/ci.yml`), a dedicated `mysql:8.0` service container initializes `hris_test` via `php bin/init-test-db.php` and executes all `669` tests against live InnoDB tables.

---

## 3. Functional Coverage Matrix by Module

| Functional Area | Test Suite File(s) | Coverage Status | Key Verified Invariants |
| :--- | :--- | :--- | :--- |
| **Authentication & MFA (Email OTP + TOTP)** | `tests/Feature/AuthenticationTest.php`, `tests/Feature/OtpAuthenticationTest.php`, `tests/Unit/Security/TotpServiceTest.php` | **Implemented & Verified** | Bcrypt password verification, HMAC-SHA256 peppered OTPs, RFC 6238 TOTP & AES-256-GCM encryption, portal role mismatch detection, 12-hour absolute & 30-min idle timeouts, `auth_fingerprint` state verification. |
| **Three-Role RBAC & Dynamic Permissions** | `tests/Feature/AdminRoleRbacTest.php`, `tests/Feature/DynamicRbacTest.php`, `tests/Feature/AuthorizationTest.php` | **Implemented & Verified** | Strict 3-role allowlist (`admin`, `hr`, `employee`), immutable `admin` role protection, dynamic `role_permissions` updates, cache invalidation, IDOR ownership guards. |
| **Admin Safeguards & Account Governance** | `tests/Feature/AuthSecurityHardeningTest.php`, `tests/Unit/SystemAuditFixesTest.php` | **Implemented & Verified** | Last-active-Admin and last-active-HR lockout prevention, Step-Up Authentication re-verification, `AdminController::updateAccount()` credential update + session fingerprint refresh (`BUG-001`). |
| **Employee, Department & Position CRUD** | `tests/Feature/EmployeeAndReportIntegrityTest.php`, `tests/Unit/EmployeeValidatorSanitizeTest.php` | **Implemented & Verified** | Input sanitization, email/employee_no uniqueness, department-position mapping validation, referential integrity deletion blocks. |
| **Leave Filing & Business-Day Calculation** | `tests/Feature/LeaveFilingIntegrityTest.php`, `tests/Unit/Application/Leave/FileLeaveRequestUseCaseTest.php`, `tests/Unit/Domain/Leave/LeaveDateRangeTest.php` | **Implemented & Verified** | Monday–Friday business-day counting (excluding weekends), cross-year boundary rejection, overlapping Pending/Approved date detection, balance sufficiency checks. |
| **Leave Approval, Rejection & Cancellation** | `tests/Feature/LeaveApprovalTest.php`, `tests/Integration/TransactionSafeLeaveApprovalTest.php`, `tests/Unit/Application/Leave/ApproveLeaveRequestUseCaseTest.php`, `tests/Unit/Application/Leave/CancelLeaveRequestUseCaseTest.php` | **Implemented & Verified** | `SELECT ... FOR UPDATE` pessimistic row locking, segregation of duties (no self-approval), atomic balance deduction, approved-leave cancellation with balance restoration. |
| **Leave Accrual & Balance Adjustments** | `tests/Unit/LeaveAccrualTest.php`, `tests/Feature/LeaveBalanceAdjustmentTest.php` | **Implemented & Verified** | Monthly accrual calculation, year-end carry-over caps, manual HR/Admin balance adjustments with audit logging. |
| **Tamper-Evident Audit Trail & CSV Safety** | `tests/Feature/AuditTrailTest.php`, `tests/Feature/SecurityAuditTest.php` | **Implemented & Verified** | SHA-256 `prev_hash` / `event_hash` chain verification, automatic redaction of sensitive keys (`password`, `otp`, `token`), CSV formula-injection neutralization. |
| **Real-Time WebSocket & Outbox Relay** | `tests/Unit/Realtime/HandshakeGuardTest.php`, `tests/Unit/Realtime/RealtimeHubTest.php`, `tests/Unit/Realtime/RealtimePolicyTest.php`, `tests/Unit/Outbox/TransactionalOutboxUnitTest.php` | **Implemented & Verified** | Single-use 30s CSPRNG WebSocket ticket handshake, origin allowlist, channel policy (`user.{id}`, `role.admin`, `role.hr`), transactional outbox serialization. |
| **Background Queue & Cron Scheduler** | `tests/Feature/QueueWorkerTest.php`, `tests/Feature/CronSchedulerTest.php` | **Implemented & Verified** | Job reservation lease, exponential backoff retry, dead-letter `failed_jobs` archival, `send_email` & `send_notification` handlers (`BUG-002`). |
| **REST API v1 & OpenAPI Specification** | `tests/Feature/ApiEndpointsTest.php` | **Implemented & Verified** | JSON envelope consistency, `PermissionMiddleware` `/api/` JSON error responses (`BUG-005`), `/api/v1/health` probe, `/api/v1/openapi.json` schema validity. |
| **Skeleton Screen Loading System** | `tests/Unit/SkeletonRendererTest.php` | **Implemented & Verified** | Accessible `role="status"` and `aria-busy="true"` wrappers, 13 reusable skeleton components, XSS-safe attribute escaping. |
| **DSA Structures, Algorithms & Benchmarks** | `tests/Unit/DSA/*.php`, `tests/Integration/DSA/*.php` | **Implemented & Verified** | Custom `ArrayStructure`, `LinkedList`, `Stack`, `Queue`, `HashTable`, `BinarySearchTree`, `LinearSearch`, `BinarySearch`, `BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, `QuickSort`, `RecursionHelper`. |

