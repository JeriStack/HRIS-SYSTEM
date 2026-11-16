# NEXORA HR — Authoritative System Workflows & Flowcharts

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16 — Reconciled End-to-End System Flowcharts  
**Implementation Status:** Implemented and Verified against Source Code  

---

## 1. Authentication & Authorization Workflow

Traces `AuthController::login()`, `AuthController::verifyOtp()`, `AuthController::verifyTotp()`, `Core\Session::verifyAuthenticatedAccountState()`, and `Core\PermissionMiddleware`.

```mermaid
flowchart TD
    A(["Visitor Opens /login"]) --> B["Select Portal Card:\nAdmin Portal | HR Portal | Employee Portal"]
    B --> C["Submit Email + Password + portal_role + CSRF Token"]
    C --> D{"Rate Limit Exceeded?\n(Account > 5 or IP > 15 in 15m)"}
    D -- "Yes" --> E["Log AUTH_RATE_LIMITED & Return 429 / Error Flash"]
    D -- "No" --> F{"User Exists, Active &\nPassword Hash Matches?"}
    F -- "No" --> G["Log AUTH_FAILED & Return Generic Credential Error"]
    F -- "Yes" --> H{"Linked Employee Status\n== Terminated / Inactive?"}
    H -- "Yes" --> I["Log AUTH_BLOCKED_INACTIVE & Deny Sign-In"]
    H -- "No" --> J{"portal_role Matches\nUser's Assigned Role?"}
    J -- "No" --> K["Log AUTH_PORTAL_MISMATCH &\nDirect User to Correct Portal (Admin / HR / Employee)"]
    J -- "Yes" --> L["Generate 6-Digit CSPRNG OTP\nHash with HMAC-SHA256 Pepper + Bcrypt\nSend via SMTP & Redirect to /verify-otp"]
    L --> M["User Submits 6-Digit Email OTP (or TOTP Backup Code)"]
    M --> N{"OTP Valid, Unexpired (<=10m)\n& Attempts < 5?"}
    N -- "No" --> O["Increment Attempts & Return Verification Error"]
    N -- "Yes" --> P["Mark OTP Used + Session::regenerate()\nStore Session User + Compute SHA-256 auth_fingerprint\nRecord last_mfa_verified_at"]
    P --> Q{"Role Routing"}
    Q -- "admin" --> R["Redirect to /admin/dashboard"]
    Q -- "hr" --> S["Redirect to /hr/dashboard"]
    Q -- "employee" --> T["Redirect to /employee/dashboard"]
```

---

## 2. Administrator Governance Workflow

Traces `AdminController` and `UserController` operations exclusively accessible to `role = 'admin'`.

```mermaid
flowchart TD
    Start(["Admin Authenticated (/admin/dashboard)"]) --> Nav{"Select Governance Action"}
    Nav -- "User Accounts (/users)" --> U1["Inspect Accounts, Provision User,\nChange Role (admin/hr/employee), or Toggle Active/Inactive"]
    U1 --> U2{"Step-Up Auth Valid\n(MFA <= 15m or Password Confirmed)?"}
    U2 -- "No" --> U3["Reject & Prompt Current Password Confirmation"]
    U2 -- "Yes" --> U4{"Self-Demotion or\nLast Active Admin/HR?"}
    U4 -- "Yes" --> U5["Block Operation (Lockout Safeguard)"]
    U4 -- "No" --> U6["Update users.role + users.role_id + status\nRevoke Active WebSocket Tickets & Sessions\nWrite Tamper-Evident Audit Log"]

    Nav -- "Roles & Permissions (/admin/roles)" --> R1["Inspect 3-Role Matrix (admin, hr, employee)"]
    R1 --> R2{"Target Role == admin?"}
    R2 -- "Yes" --> R3["Block Mutation: Admin Role is System-Protected & Immutable"]
    R2 -- "No (hr or employee)" --> R4["Validate Permission Slugs against PermissionRegistry\nTransactionally Replace role_permissions\nInvalidate Cache & Record Diff in Audit Trail"]

    Nav -- "Admin Provisioning (/admin/provisioning)" --> P1["Verify Current Admin Password + Validate New Admin Details\nCreate Linked Admin Account + Log ADMIN_PROVISIONED"]
    Nav -- "HR Support Management (/admin/support)" --> SUP1["Inspect HR Support Inbox & Live DB KPIs\nAssign Active Admin, Change Priority/Status, Reply to HR,\nRequest Info, or Log Private Internal Note (Hidden from HR)"]
    Nav -- "My Account (/admin/account)" --> A1["Verify Current Password (findByIdWithAuthState)\nUpdate Email/Password via User::update()\nRefresh Session auth_fingerprint & Regenerate Session ID"]
    Nav -- "Security & Health (/admin/security-events, /admin/system-health)" --> S1["Inspect Security Events & Run Live Socket/DB Health Probes"]
```

---

## 3. HR Officer Operational Workflow

Traces `DashboardController`, `EmployeeController`, `DepartmentController`, `PositionController`, `LeaveController`, `ReportController`, and `HrAdminSupportController`.

```mermaid
flowchart TD
    Start(["HR Officer Authenticated (/hr/dashboard)"]) --> Task{"Select HR Operation"}
    Task -- "Workforce Directory (/employees)" --> E1["Search, Filter by Dept/Status, View Profile,\nCreate New Employee, Edit Details, or Update Employment Status"]
    Task -- "Org Structure (/departments, /positions)" --> O1["Create/Edit/Delete Departments & Job Positions\n(Deletion Blocked if Employees Assigned)"]
    Task -- "Leave Review Queue (/leaves)" --> L1["Inspect Pending Leave Requests or Process Next in FIFO Queue"]
    L1 --> L2["Approve, Reject (with Reason), or Cancel Approved Leave (with Reason)"]
    Task -- "Leave Policies & Balances (/leaves/types, /leaves/balances)" --> B1["Create Leave Types or Adjust Individual Employee Leave Balances"]
    Task -- "Help & Support to Admin (/hr/help)" --> H1["Submit Technical/Operational Ticket (HR-SUP-YYYY-XXXXXXXX)\nTrack Own Tickets, Reply, Confirm Resolution, or Reopen within 14d"]
    Task -- "Analytics & Audit (/reports, /activity-logs)" --> R1["Filter Workforce/Leave Analytics, Export Sanitized CSV,\nor Inspect Hash-Chained Audit Trail"]
```

---

## 4. Employee Self-Service Workflow

Traces `DashboardController::index()`, `DashboardController::profile()`, `LeaveController::create()`, `LeaveController::store()`, `LeaveController::cancel()`, and `NotificationController`.

```mermaid
flowchart TD
    Start(["Employee Authenticated (/employee/dashboard)"]) --> Action{"Select Self-Service Action"}
    Action -- "My Profile (/profile)" --> P1["View Personal Employment Details, Department, Position & Status"]
    Action -- "My Leave Balances (/leaves/balances)" --> B1["View Annual Allocated, Used & Remaining Days per Leave Type"]
    Action -- "Apply for Leave (/leaves/create)" --> L1["Select Leave Type, Start Date, End Date & Reason\nLive Working-Day Preview & Submit Application"]
    Action -- "My Leave History (/leaves)" --> H1["Track Request Status (Pending, Approved, Rejected, Cancelled)"]
    H1 --> H2{"Request Status == Pending?"}
    H2 -- "Yes" --> H3["Optional: Withdraw/Cancel Own Pending Request\n(POST /leaves/{id}/cancel)"]
    Action -- "Notifications (/notifications)" --> N1["Receive Live WebSocket Toast Alerts &\nMark Notifications Read"]
```

---

## 5. Employee Management & Auto-Provisioning Workflow

Traces `EmployeeController::store()`, `EmployeeController::update()`, and `EmployeeController::deactivate()`.

```mermaid
flowchart TD
    A(["HR/Admin Submits New Employee Form\n(POST /employees/store)"]) --> B["EmployeeValidator::sanitizeAndValidate()\nCheck Required Fields, Email Format, Dates & Dept/Position Match"]
    B --> C{"Validation Passed &\nEmail/Employee No Unique?"}
    C -- "No" --> D["Flash Validation Errors & Redirect Back with Old Input"]
    C -- "Yes" --> E["Insert Employee Record into employees Table"]
    E --> F["Auto-Provision Linked User Account in users Table\n(role = employee, role_id = employee) if Requested"]
    F --> G["Initialize Annual Leave Balances in leave_balances\nfor All Configured Leave Types"]
    G --> H["Record Audit Log (EMPLOYEE_CREATED) &\nBroadcast Real-Time Dashboard Update"]
```

---

## 6. Leave Request Submission Workflow

Traces `LeaveController::store()` -> `LeaveControllerAdapter::file()` -> `FileLeaveRequestUseCase::execute()`.

```mermaid
flowchart TD
    A(["Employee Submits POST /leaves/store"]) --> B["Verify CSRF Token & Active Employee Identity"]
    B --> C["Validate Start Date <= End Date & Same Calendar Year"]
    C --> D["LeaveCalculator::calculateBusinessDays(startDate, endDate)\nCount Monday-Friday Working Days"]
    D --> E{"Business Days > 0?"}
    E -- "No (Weekend Only)" --> F["Reject: Selected range contains 0 working days"]
    E -- "Yes" --> G["MySqlLeaveFilingGuard:\nCheck Overlapping Pending/Approved Leave Requests"]
    G --> H{"Overlap Detected?"}
    H -- "Yes" --> I["Reject: Overlapping leave request already exists"]
    H -- "No" --> J["Check leave_balances.remaining_days >= requestedDays"]
    J --> K{"Sufficient Balance?"}
    K -- "No" --> L["Reject: Insufficient remaining leave balance"]
    K -- "Yes" --> M["BEGIN TRANSACTION\nInsert leave_requests (status = 'Pending')\nInsert LeaveRequestSubmitted into realtime_outbox\nCOMMIT"]
    M --> N["Create In-App Notification for HR/Admin &\nRecord Audit Log (LEAVE_FILED)"]
```

---

## 7. Leave Approval, Rejection & Cancellation Workflows

### 7.1 Leave Approval & Rejection (`ApproveLeaveRequestUseCase` / `RejectLeaveRequestUseCase`)

```mermaid
flowchart TD
    A(["HR or Admin Submits Approve / Reject\n(POST /leaves/{id}/approve or /reject)"]) --> B{"Actor Role in\n['admin', 'hr']?"}
    B -- "No" --> C["403 Forbidden Redirect"]
    B -- "Yes" --> D["BEGIN TRANSACTION\nSELECT * FROM leave_requests WHERE id = :id FOR UPDATE"]
    D --> E{"Request Exists &\nstatus == 'Pending'?"}
    E -- "No" --> F["ROLLBACK & Flash Invalid State Transition Error"]
    E -- "Yes" --> G{"Reviewer Owns Request?\n(Self-Approval Check)"}
    G -- "Yes" --> H["ROLLBACK & Reject: Segregation of Duties Violation"]
    G -- "No" --> I{"Action == Approve?"}
    I -- "No (Reject)" --> J["UPDATE leave_requests SET status='Rejected', rejection_reason=:r\nInsert LeaveRequestRejected into realtime_outbox\nCOMMIT"]
    I -- "Yes (Approve)" --> K["SELECT * FROM leave_balances WHERE emp/type/year FOR UPDATE"]
    K --> L{"remaining_days >=\nnumber_of_days?"}
    L -- "No" --> M["ROLLBACK & Reject: Insufficient Balance"]
    L -- "Yes" --> N["UPDATE leave_balances (used_days += N, remaining_days -= N)\nUPDATE leave_requests SET status='Approved', approved_by=:uid\nInsert LeaveRequestApproved into realtime_outbox\nCOMMIT"]
    J & N --> O["LeaveNotificationService::leaveDecided():\nCreate In-App Notification + Enqueue send_email Job in jobs Table\nRecord SHA-256 Audit Log"]
```

### 7.2 Leave Cancellation & Balance Refund (`CancelLeaveRequestUseCase`)

```mermaid
flowchart TD
    A(["Actor Submits POST /leaves/{id}/cancel"]) --> B["BEGIN TRANSACTION\nLock leave_requests Row (FOR UPDATE)"]
    B --> C{"Request Status?"}
    C -- "Pending" --> D{"Actor is Owner OR\nRole in ['admin', 'hr']?"}
    D -- "No" --> E["ROLLBACK & Deny (IDOR Protection)"]
    D -- "Yes" --> F["UPDATE leave_requests SET status = 'Cancelled'\nInsert LeaveRequestCancelled into realtime_outbox\nCOMMIT"]
    C -- "Approved" --> G{"Actor Role in ['admin', 'hr']\nAND cancellation_reason Provided?"}
    G -- "No" --> H["ROLLBACK & Reject (Employees Cannot Self-Cancel Approved Leave;\nHR/Admin Must Provide Reason)"]
    G -- "Yes" --> I["Lock leave_balances Row (FOR UPDATE)\nRestore Balance: used_days -= N, remaining_days += N\nUPDATE leave_requests SET status = 'Cancelled', rejection_reason = :reason\nInsert LeaveRequestCancelled into realtime_outbox\nCOMMIT + Notify Employee + Audit Log"]
```

---

## 8. Notification, Transactional Outbox & Background Queue Workflow

Traces `realtime_outbox`, `app/realtime/Server.php`, `app/Queue/QueueService.php`, and `app/Queue/QueueWorker.php`.

```mermaid
flowchart LR
    subgraph SyncTx["1. Synchronous Business Transaction"]
        Biz["Controller / UseCase"] --> CommitDB[("MySQL InnoDB\nCommit Business Row +\nrealtime_outbox + jobs")]
    end

    subgraph RealtimePush["2. Real-Time WebSocket Delivery (:8090)"]
        CommitDB --> Poller["PdoRealtimeStore::claimOutboxBatch()"]
        Poller --> AuthZ["EventAuthorizer & ChannelPolicy\n(user.{id}, role.admin, role.hr)"]
        AuthZ --> PushWS["Push JSON Frame to Connected Browsers"]
    end

    subgraph AsyncWorker["3. Background Queue Worker (bin/queue-worker.php)"]
        CommitDB --> Lease["QueueWorker::reserveNextJob()\n(FOR UPDATE Lease)"]
        Lease --> Dispatch["JobRegistry Dispatches Handler:\nSendEmailHandler | SendNotificationHandler |\nReportExportHandler | LeaveReminderHandler |\nOutboxRelayHandler"]
        Dispatch -- "Success" --> DeleteJob["Delete Completed Row from jobs"]
        Dispatch -- "Exception (Attempts < Max)" --> Backoff["Release Lease with Exponential Backoff"]
        Dispatch -- "Attempts >= Max" --> DeadLetter["Move to failed_jobs Table"]
    end
```

---

## 9. Tamper-Evident Audit Trail Workflow

Traces `Core\AuditService::record()` and `Core\AuditService::verifyChainIntegrity()`.

```mermaid
flowchart TD
    A(["System Action Triggered\n(Auth, RBAC, Employee, Leave, Export)"]) --> B["AuditService::record(event)"]
    B --> C["Sanitize & Redact Sensitive Keys in old_values / new_values\n(password, otp, secret, token, csrf_token -> [REDACTED])"]
    C --> D["Fetch Latest event_hash from activity_logs as prev_hash\n(or GENESIS_000...000 if first record)"]
    D --> E["Compute Canonical Payload:\nevent_id | user_id | actor_role | action | category | entity_type | entity_id |\nold_json | new_json | status | ip_address | created_at | prev_hash"]
    E --> F["Compute event_hash = hash('sha256', canonicalPayload)"]
    F --> G["INSERT INTO activity_logs (..., prev_hash, event_hash)"]
    G --> H["Verification: AuditService::verifyChainIntegrity()\nRecomputes Every Row's Hash & Confirms prev_hash == prior.event_hash"]
```

---

## 10. Deployment & CI/CD Pipeline Workflow

Traces `.github/workflows/ci.yml` and `docker-compose.yml`.

```mermaid
flowchart LR
    Push(["Git Push / Pull Request"]) --> GHA["GitHub Actions CI (.github/workflows/ci.yml)"]
    GHA --> Job1["1. actionlint\nWorkflow Validation"]
    GHA --> Job2["2. PHP 8.5 Syntax (php -l)\nPSR-12 Baseline (check-phpcs-baseline.php)\nPHPStan Static Analysis\nDSA Unit Tests & Full PHPUnit Suite (MySQL 8.0)"]
    GHA --> Job3["3. Terraform fmt, init & validate\n(Root, Dev, Prod + Provider Cache)"]
    GHA --> Job4["4. Hadolint & Docker Image Build\n+ Container Health Smoke Test"]
    Job1 & Job2 & Job3 & Job4 --> Deploy["Docker Compose Stack Startup\n(nginx, app, realtime, worker, scheduler,\ndb, prometheus, grafana, swagger-ui, mailpit)"]
```

