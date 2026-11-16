# NEXORA HR — Anticipated Defense Panel Questions & Verified Answers

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.5 — Panel Q&A Bank (Grounded in Verified Source Code)

---

## 1. General & Product Questions

### Q1.1: What problem does NEXORA HR solve, and why did you develop it?
**Answer:**  
NEXORA HR solves three core operational problems in workforce and leave administration:
1. **Disconnected employee and access records** — solved by linking `employees`, `departments`, `positions`, and `users` with strict foreign-key constraints and live session synchronization.
2. **Inaccurate and race-condition-prone leave accounting** — solved by automatic Monday–Friday business-day calculation (`LeaveCalculator`), overlap detection (`MySqlLeaveFilingGuard`), and pessimistic row locking (`SELECT ... FOR UPDATE`) during leave approvals.
3. **Lack of governance separation and auditability** — solved by our 3-role dynamic RBAC (`admin`, `hr`, `employee`), Step-Up Authentication, and SHA-256 hash-chained audit trail (`AuditService`).

### Q1.2: Who are the intended users, and how are their responsibilities separated?
**Answer:**  
The system enforces three mutually distinct roles (`Core\PermissionRegistry::ROLES`):
- **Administrator (`admin`):** System governance, user account provisioning, dynamic role-permission management (`/admin/roles`), security events, system health probes, and full audit oversight.
- **HR Officer (`hr`):** Daily workforce CRUD (`/employees`, `/departments`, `/positions`), leave review queue and balance management (`/leaves`), and analytical CSV reporting (`/reports`). HR officers are explicitly blocked from Admin governance routes.
- **Employee (`employee`):** Self-service access restricted to their own profile (`/profile`), their own leave balances, filing leave requests (`/leaves/create`), withdrawing their own pending requests, and receiving real-time notifications.

---

## 2. Architecture & Database Questions

### Q2.1: Why did you choose a custom PHP 8.5 MVC + Clean Architecture hybrid instead of a heavy third-party framework?
**Answer:**  
Building a custom lightweight MVC core (`Core\Router`, `Core\Controller`, `Core\Model`, `Core\Session`) paired with Clean Architecture use cases (`App\Application\Leave\UseCases\*`) gave us:
1. **Complete transparency for academic and security auditing** — every middleware check, SQL query, and data structure is explicit and verifiable without magic framework abstractions.
2. **High performance and tiny memory footprint** — our entire 669-test suite runs in ~8.3 seconds using only 48 MB of RAM, and our RBAC evaluation takes `0.0036 ms` median (`55,085 ops/sec`).
3. **Strict domain isolation** — `ApproveLeaveRequestUseCase` and `FileLeaveRequestUseCase` can be unit-tested with in-memory fakes (`InMemoryLeaveRequestRepository`, `InMemoryLeaveBalanceRepository`) in milliseconds, and also run against MySQL InnoDB in production.

### Q2.2: How is the database structured, and how do you enforce referential integrity?
**Answer:**  
The database (`database/schema.sql`) contains **18 InnoDB tables** using `utf8mb4_unicode_ci`. Referential integrity is enforced at the database engine level via foreign keys:
- `ON DELETE RESTRICT` on `employees.department_id`, `employees.position_id`, and `leave_requests.leave_type_id` prevents deleting departments, positions, or leave types that are referenced by employee or leave records.
- `ON DELETE CASCADE` on `leave_balances.employee_id`, `notifications.user_id`, and `email_otps.user_id` ensures dependent records are cleaned up cleanly.
- `ON DELETE SET NULL` on `activity_logs.user_id` and `leave_requests.approved_by` preserves historical audit logs and approval records even if a user account is later removed.

### Q2.3: How does the frontend communicate with the backend for both synchronous actions and real-time updates?
**Answer:**  
- **Synchronous actions** use Server-Side Rendered (SSR) HTML forms with CSRF tokens (`POST /leaves/store`, `POST /employees/store`) or JSON REST API v1 endpoints (`/api/v1/*`). During asynchronous fetches, `skeleton.js` displays accessible `aria-busy="true"` shimmer placeholders (`SkeletonRenderer`).
- **Real-time updates** use a persistent WebSocket connection (`/ws` proxied by Nginx to the Ratchet server on port `:8090`). The browser first calls `POST /realtime/ticket` to obtain a single-use 30-second CSPRNG ticket, then connects to `/ws` to receive instant push notifications from `realtime_outbox`.

---

## 3. Security & Access Control Questions

### Q3.1: How does authentication work, and how are OTP codes protected in the database?
**Answer:**  
Authentication is a two-factor process in `AuthController`:
1. **Factor 1 (Password + Portal Role):** Verified via `password_verify()` against a Bcrypt hash (`cost = 12`), plus server-side verification that the selected login portal (`admin`, `hr`, or `employee`) matches `users.role`.
2. **Factor 2 (Email OTP or TOTP):** A 6-digit code is generated via `random_int(100000, 999999)`. Before hashing with Bcrypt, `EmailOtp::hashOtp()` computes an HMAC-SHA256 digest using the user ID and server-side `OTP_PEPPER_SECRET`. Even if the `email_otps` table were leaked, an attacker cannot brute-force the 6-digit space without the server pepper secret. TOTP secrets in `user_totp` are encrypted at rest using `AES-256-GCM`.

### Q3.2: Why is public Administrator registration prohibited, and how is the first Admin created?
**Answer:**  
Allowing a public `/register` endpoint to create Administrator accounts is a critical privilege-escalation risk. In NEXORA HR:
- There is no public registration route.
- The initial Administrator is provisioned via the CLI-only script `bin/create_admin_user.php` (which checks `php_sapi_name() === 'cli'` and refuses web execution).
- Subsequent Administrators can only be provisioned by an authenticated Admin on `/admin/provisioning` after passing Step-Up Authentication (confirming their current password).

### Q3.3: How does the application prevent privilege escalation or stale sessions when a user is deactivated or demoted?
**Answer:**  
Two mechanisms work together:
1. **`Session::verifyAuthenticatedAccountState()`:** Executed by `AuthMiddleware` on every request. It queries `User::findByIdWithAuthState($userId)` and compares the live SHA-256 `auth_fingerprint` (`id|role|status|employment_status|password_hash`) against the session's stored fingerprint. Any role change, status deactivation, employee termination, or password change immediately destroys the session.
2. **`AccountRevocationService`:** Immediately revokes any unconsumed WebSocket tickets in `realtime_tickets` and records a revocation entry so active WebSocket connections are closed.

---

## 4. HRIS Business Logic & Leave Management Questions

### Q4.1: How are leave days calculated, and how are duplicate or overlapping requests prevented?
**Answer:**  
- **Business-Day Calculation (`LeaveCalculator::calculateWorkingDays()`):** Iterates through the inclusive date range `[start_date, end_date]` using `DateTimeImmutable` and counts only ISO-8601 weekdays `1` (Monday) through `5` (Friday), excluding Saturdays (`6`) and Sundays (`7`).
- **Overlap Prevention (`MySqlLeaveFilingGuard`):** Executes an indexed range query (`idx_requests_emp_dates`) checking whether any `Pending` or `Approved` request exists for the same `employee_id` where `existing.start_date <= new.end_date AND existing.end_date >= new.start_date`.

### Q4.2: How are leave balances updated safely under concurrent HR approvals?
**Answer:**  
In `ApproveLeaveRequestUseCase` and `Leave::approveRequest()`:
1. A database transaction is opened (`BEGIN`).
2. The target `leave_requests` row is locked via `SELECT ... FOR UPDATE`, confirming its status is still `Pending`.
3. The employee's `leave_balances` row for that leave type and year is locked via `SELECT ... FOR UPDATE`.
4. The system verifies `remaining_days >= number_of_days`, then executes `UPDATE leave_balances SET used_days = used_days + :days, remaining_days = remaining_days - :days` and marks the request `Approved`.
5. A `LeaveRequestApproved` event is inserted into `realtime_outbox`, and the transaction commits atomically.

### Q4.3: How does the Audit Trail guarantee that logs have not been tampered with?
**Answer:**  
`Core\AuditService` implements a **cryptographic hash chain**:
- Before inserting a row into `activity_logs`, it redacts sensitive keys (`password`, `otp`, `token`, `secret`), fetches the previous row's `event_hash` as `prev_hash`, and computes `event_hash = hash('sha256', canonical_payload . '|' . prev_hash)`.
- `AuditService::verifyChainIntegrity()` walks the log chain from genesis and recomputes every SHA-256 hash; any modified or deleted row breaks the chain and is flagged immediately.

---

## 5. Testing, Performance & DSA Questions

### Q5.1: How was the system tested, and what were the actual results?
**Answer:**  
The system is verified by **669 automated PHPUnit tests** (`2,528` assertions) across Unit, Integration, and Feature suites, plus **PHPStan static analysis** (`[OK] No errors`), **PSR-12 baseline verification** (`0` new violations), and **Selenium E2E browser tests** (`e2e-tests/`). During our Phase 16 full-system functional audit, 7 defects (`BUG-001` through `BUG-007`) were identified, fixed, and verified with dedicated regression tests (`SystemAuditFixesTest.php`).

### Q5.2: Why is the DSA module included, and which structures and algorithms are used in real HRIS workflows?
**Answer:**  
The DSA module fulfills the academic Computer Science requirement while solving concrete in-memory operations inside NEXORA HR:
- **FIFO `Queue` (`App\Dsa\Structures\Queue`):** Powers `LeaveApprovalQueueService` (`POST /leaves/queue/next`) so HR can process pending leave applications in strict chronological arrival order ($O(1)$ enqueue/dequeue).
- **Polynomial `HashTable` (`App\Dsa\Structures\HashTable`):** Provides $O(1)$ average employee lookup (`0.0009 ms` median at $N=1,000$, over 1,000x faster than Linear Search).
- **`MergeSort` & `QuickSort`:** Provide $O(n \log n)$ sorting (`~9.7 ms` median at $N=1,000$).
- **`BinarySearch` & `BinarySearchTree`:** Provide $O(\log n)$ logarithmic lookup (`0.0529 ms` median at $N=1,000$ for Binary Search).
- **`Stack` & `LinkedList`:** Used for LIFO activity inspection and pointer-chained lists, all interactive and traceable in the `/dsa` laboratory.

