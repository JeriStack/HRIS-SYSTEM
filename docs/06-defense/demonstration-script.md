# NEXORA HR — Live Project Defense Demonstration Script (15-Step Walkthrough)

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.5 — Live Demonstration Script  
**Security Note:** No real passwords or production secrets are embedded in this document. Use local seeded test accounts + Mailpit (`http://localhost:8025`) for live 2FA OTP retrieval.

---

## Pre-Demonstration Readiness Check (5 Minutes Before Defense)

1. Verify containers or local services are running (`docker compose ps` or local XAMPP + `php bin/realtime-server.php` + `php bin/queue-worker.php`).
2. Open three browser tabs/windows:
   - **Tab 1:** NEXORA HR Application (`http://localhost:8080/login`)
   - **Tab 2:** Mailpit 2FA OTP Inbox (`http://localhost:8025`)
   - **Tab 3:** Swagger UI (`http://localhost:8083`) & Grafana (`http://localhost:3100`)

---

## Step-by-Step Live Demonstration Sequence

### Step 1: Introduce NEXORA HR & Three-Role Portal Landing Screen
- **Account / Role Required:** Unauthenticated Visitor
- **Navigation Path:** `GET /login`
- **Action to Perform:** Show the NEXORA HR landing screen (`Welcome to NEXORA HR — People. Processes. Progress.`) and highlight the three distinct portal cards: **Admin Portal**, **HR Portal**, and **Employee Portal**. Click **Continue as Admin** to open the accessible Admin Portal modal (`role="dialog"`).
- **Expected Result:** Modal displays `Admin Portal Login` badge, shield icon, Email/Password fields, and `2FA Email OTP Enabled` indicator.
- **Screenshot / Evidence:** `docs/screenshots/admin/admin-portal-login.png`
- **Backup Plan:** Show `docs/screenshots/admin/admin-portal-login.png` and `app/views/auth/login.php`.

### Step 2: Demonstrate Administrator Login, 2FA Email OTP & Admin Dashboard
- **Account / Role Required:** Administrator (`role = 'admin'`)
- **Navigation Path:** `POST /login` -> `GET /verify-otp` -> `POST /verify-otp` -> `GET /admin/dashboard`
- **Action to Perform:** Sign in with the Administrator test account, retrieve the 6-digit OTP from Mailpit (`http://localhost:8025`), submit the OTP on `/verify-otp`, and land on `/admin/dashboard`.
- **Expected Result:** Session regenerates with a cryptographic `auth_fingerprint` and renders the Administrator Governance Dashboard.
- **Screenshot / Evidence:** `docs/screenshots/admin/admin-dashboard.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/AdminRoleRbacTest.php` in terminal to prove live Admin authentication and RBAC enforcement.

### Step 3: Demonstrate Dynamic RBAC Matrix & User Governance
- **Account / Role Required:** Administrator (`role = 'admin'`)
- **Navigation Path:** `GET /admin/roles` & `GET /users`
- **Action to Perform:**
  1. Open **Roles & Permissions** (`/admin/roles`) and show the 4 governance sections: **System Roles Overview** (`SYSTEM PROTECTED` on Admin), **Permission Matrix** (`perm-toggle-switch`), **User Access Management**, and **Permission Audit History**.
  2. Point out that Admin toggles are locked/immutable (`AuthorizationService` blocks any mutation of `admin`), while `hr` and `employee` capabilities are database-driven (`role_permissions`).
  3. Open **User Accounts** (`/users`) and show the Step-Up Authentication and last-active-Admin/HR lockout safeguards.
- **Expected Result:** Changes to `hr` or `employee` permissions are persisted transactionally and logged with before/after JSON diffs.
- **Screenshot / Evidence:** `docs/screenshots/admin/admin-permission-management.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/DynamicRbacTest.php`.

### Step 4: Sign In to HR Portal & Inspect HR Operational Dashboard
- **Account / Role Required:** HR Officer (`role = 'hr'`, e.g., `marcus.tan@company.local`)
- **Navigation Path:** `POST /logout` -> `GET /login` -> **HR Portal** -> `/hr/dashboard`
- **Action to Perform:** Sign in via the **HR Portal** card, complete 2FA OTP verification, and inspect `/hr/dashboard`.
- **Expected Result:** Displays total workforce headcount, active employees, pending leave requests, department distribution charts, and live WebSocket connection status.
- **Screenshot / Evidence:** `docs/screenshots/hr/hr-portal-login.png`, `docs/screenshots/hr/hr-dashboard.png`
- **Backup Plan:** Show `app/controllers/DashboardController.php` and `app/views/dashboard/hr.php`.

### Step 5: Create or Inspect an Employee Record
- **Account / Role Required:** HR Officer (`role = 'hr'`)
- **Navigation Path:** `GET /employees` -> `GET /employees/create` -> `GET /employees/{id}`
- **Action to Perform:**
  1. Open the **Employee Directory** (`/employees`), filter by department or search by employee number/name.
  2. Click **+ Add Employee** (`/employees/create`) or open an existing employee profile (`/employees/{id}`) to show linked department, position, employment status, and auto-initialized annual leave balances.
- **Expected Result:** Employee record displays complete organizational and leave quota details.
- **Screenshot / Evidence:** `docs/screenshots/hr/hr-employee-management.png`, `docs/screenshots/hr/hr-employee-profile.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/EmployeeAndReportIntegrityTest.php`.

### Step 6: Sign In as an Employee & Inspect Self-Service Workspace
- **Account / Role Required:** Employee (`role = 'employee'`, e.g., `david.cruz@company.local`)
- **Navigation Path:** `GET /login` -> **Employee Portal** -> `GET /employee/dashboard` & `GET /profile`
- **Action to Perform:** Sign in through the **Employee Portal**, view `/employee/dashboard` and `/profile`, and demonstrate that navigating to `/admin/dashboard` or `/employees/1` is blocked with `403 Forbidden` (IDOR & RBAC protection).
- **Expected Result:** Employee sees only their own profile, leave balances, and request history.
- **Screenshot / Evidence:** `docs/screenshots/employee/employee-portal-login.png`, `docs/screenshots/employee/employee-dashboard.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/AuthorizationTest.php`.

### Step 7: Submit a New Leave Request (Business-Day Calculation)
- **Account / Role Required:** Employee (`role = 'employee'`)
- **Navigation Path:** `GET /leaves/create` -> `POST /leaves/store`
- **Action to Perform:** Select **Vacation Leave**, choose a start and end date spanning a weekend, observe `LeaveCalculator` automatically counting only Monday–Friday business days, enter a reason, and click **Submit Leave Application**.
- **Expected Result:** Request is created with `status = 'Pending'`, a `LeaveRequestSubmitted` event is written to `realtime_outbox`, and HR receives a real-time notification.
- **Screenshot / Evidence:** `docs/screenshots/employee/employee-leave-application.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/LeaveFilingIntegrityTest.php`.

### Step 8: Return to HR Account & Review the Pending Leave Queue
- **Account / Role Required:** HR Officer (`role = 'hr'`)
- **Navigation Path:** `GET /leaves?status=Pending`
- **Action to Perform:** Switch to (or sign in as) the HR Officer and open `/leaves`. Show the newly submitted `Pending` leave application and the **FIFO Approval Queue** widget (`LeaveApprovalQueueService`).
- **Expected Result:** The pending request appears at the tail of the chronological FIFO queue.
- **Screenshot / Evidence:** `docs/screenshots/leave-management/hr-leave-approval.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Integration/DSA/LeaveApprovalQueueTest.php`.

### Step 9: Approve the Leave Request (Pessimistic Locking & Outbox)
- **Account / Role Required:** HR Officer (`role = 'hr'`)
- **Navigation Path:** `POST /leaves/{id}/approve` (or `POST /leaves/queue/next`)
- **Action to Perform:** Click **Approve** on the pending leave request (or click **Process Next in Queue (FIFO)**).
- **Expected Result:** `ApproveLeaveRequestUseCase` locks the request and balance rows (`SELECT ... FOR UPDATE`), deducts the working days from `leave_balances`, marks the request `Approved`, writes `LeaveRequestApproved` to `realtime_outbox`, and enqueues an asynchronous decision email in `jobs`.
- **Screenshot / Evidence:** `docs/screenshots/leave-management/hr-leave-approval.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Unit/Application/Leave/ApproveLeaveRequestUseCaseTest.php`.

### Step 10: Return to Employee Account & Verify Updated Status, Balance & Notification
- **Account / Role Required:** Employee (`role = 'employee'`)
- **Navigation Path:** `GET /leaves`, `GET /leaves/balances`, `GET /notifications`
- **Action to Perform:** View the employee's **Leave History** (`/leaves`), **Leave Balances** (`/leaves/balances`), and **Notifications** (`/notifications`) (plus Mailpit inbox for the queued decision email).
- **Expected Result:** Request status is `Approved`, `used_days` increased by $N$, `remaining_days` decreased by $N$, and the in-app notification + email confirm the decision.
- **Screenshot / Evidence:** `docs/screenshots/employee/employee-leave-history.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/LeaveWorkflowIntegrationTest.php`.

### Step 11: Inspect the Tamper-Evident SHA-256 Hash-Chained Audit Trail
- **Account / Role Required:** Administrator (`admin`) or HR Officer (`hr`)
- **Navigation Path:** `GET /activity-logs`
- **Action to Perform:** Open **Audit Trail** (`/activity-logs`), filter by category (`leave`, `auth`, `rbac`), and inspect the recorded `LEAVE_FILED` and `LEAVE_APPROVED` events with their before/after JSON diffs and SHA-256 chain status.
- **Expected Result:** Every action is logged with actor role, IP address, correlation ID, and intact cryptographic hash chain (`prev_hash` -> `event_hash`).
- **Screenshot / Evidence:** `docs/03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/AuditTrailTest.php`.

### Step 12: Demonstrate Workforce & Leave Reports + Formula-Safe CSV Export
- **Account / Role Required:** HR Officer (`hr`) or Administrator (`admin`)
- **Navigation Path:** `GET /reports` -> `GET /reports/export`
- **Action to Perform:** Open **Reports & Analytics** (`/reports`), review the summary cards and charts, and click **Export CSV**.
- **Expected Result:** Downloads a clean RFC 4180 CSV report where every cell is sanitized through `AuditService::sanitizeCsvField()` to prevent spreadsheet formula injection.
- **Screenshot / Evidence:** `docs/screenshots/reports/hr-reports.png`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/ReportsIntegrationTest.php`.

### Step 13: Demonstrate System Health, Swagger UI & Monitoring
- **Account / Role Required:** Administrator (`admin`)
- **Navigation Path:** `GET /admin/system-health`, `GET /api/v1/health`, Swagger UI (`:8083`), Grafana (`:3100`)
- **Action to Perform:** Show the live runtime probes on `/admin/system-health`, the JSON health endpoint `/api/v1/health`, and the OpenAPI 3.0 interactive documentation at `/docs` (`:8083`).
- **Expected Result:** Live probes confirm MySQL PDO connectivity, WebSocket port `:8090` health, and PHP runtime status.
- **Backup Plan:** Run `vendor/bin/phpunit tests/Feature/ApiEndpointsTest.php`.

### Step 14: Demonstrate the Interactive DSA Laboratory
- **Account / Role Required:** Administrator (`admin`) or HR Officer (`hr`)
- **Navigation Path:** `GET /dsa`
- **Action to Perform:** Open **DSA Laboratory** (`/dsa`) and execute live demonstrations of **Binary Search vs. Linear Search**, **Merge Sort vs. Quick Sort**, **FIFO Queue**, **LIFO Stack**, **Polynomial Hash Table**, and **Binary Search Tree** traversal on HRIS workforce records.
- **Expected Result:** Displays step-by-step execution traces (`DsaTraceService`), comparison counts, and microsecond execution durations.
- **Screenshot / Evidence:** `docs/screenshots/dsa/chart_search_execution_time.svg`, `docs/screenshots/dsa/chart_sorting_execution_time.svg`
- **Backup Plan:** Run `vendor/bin/phpunit tests/Unit/DSA` (`100%` passing).

### Step 15: Conclude with Automated Quality Gate Execution
- **Account / Role Required:** Presenter CLI Terminal
- **Action to Perform:** Run `php bin/check-phpcs-baseline.php` and `vendor/bin/phpunit` live in the terminal to show **669 tests passing (`0` failures, `0` errors)** and **0 new PSR-12 violations**.

