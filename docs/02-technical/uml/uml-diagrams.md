# NEXORA HR — Authoritative UML Diagrams Catalog

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16 — UML Specifications (Context, Use Case, Class, Sequence, Activity, Component, Deployment)  
**Implementation Status:** Implemented and Verified against Source Code  

---

## 1. System Context Diagram

Shows the boundary of **NEXORA HR** and its interactions with the three approved human actors (`Admin`, `HR`, `Employee`) and external/supporting infrastructure systems (`SMTP Mail Relay`, `Prometheus/Grafana Telemetry`).

```mermaid
flowchart LR
    subgraph Actors["Human Actors"]
        Admin["🛡️ Administrator"]
        HR["💼 HR Officer"]
        Emp["👤 Employee"]
    end

    subgraph SystemBoundary["NEXORA HR System Boundary"]
        CoreHRIS["NEXORA HR Platform\n(PHP 8.5 MVC + Clean Architecture +\nMySQL 8.0 InnoDB + Ratchet WebSocket)"]
    end

    subgraph ExternalSystems["External & Infrastructure Systems"]
        SMTP["📧 SMTP Server / Mailpit\n(2FA OTP & Leave Notifications)"]
        Obs["📊 Prometheus & Grafana\n(Metrics & Health Telemetry)"]
        Browser["🌐 Chromium / Brave Browser\n(HTTPS, REST API v1, WebSocket /ws)"]
    end

    Admin & HR & Emp --> Browser
    Browser -- "HTTP/HTTPS & WebSocket (/ws)" --> CoreHRIS
    CoreHRIS -- "RFC 821 SMTP (PHPMailer)" --> SMTP
    Obs -- "Scrapes /stub_status (:9113)\n& MySQL Metrics (:9104)" --> CoreHRIS
```

---

## 2. UML Use Case Diagram (Three-Role RBAC Model)

Represents the exact capabilities enforced by `Core\PermissionRegistry`, `Core\AuthorizationService`, `Core\RoleMiddleware`, and `Core\PermissionMiddleware`.

```mermaid
flowchart LR
    Admin(["🛡️ Administrator\n(role = admin)"])
    HR(["💼 HR Officer\n(role = hr)"])
    Emp(["👤 Employee\n(role = employee)"])

    subgraph AuthBoundary["Authentication & Security"]
        UC_Login("Sign In via Portal + 2FA Email OTP / TOTP")
        UC_StepUp("Verify Step-Up Authentication for Sensitive Actions")
    end

    subgraph AdminBoundary["System Governance (Admin Only)"]
        UC_AdminDash("View Admin Governance Dashboard")
        UC_ManageUsers("Provision, Activate/Deactivate Users & Assign Roles")
        UC_ManageRBAC("Configure Dynamic Role-Permission Matrix")
        UC_ProvisionAdmin("Provision Additional Administrator Account")
        UC_SecEvents("Monitor Security Events & System Health")
        UC_SysSettings("Manage Auth, General & Notification Settings")
    end

    subgraph WorkforceBoundary["Workforce & Leave Operations (HR & Admin)"]
        UC_HRDash("View HR Operational Dashboard")
        UC_EmpCRUD("Create, View, Edit & Deactivate Employee Records")
        UC_DeptPos("Manage Departments & Job Positions")
        UC_LeaveReview("Approve / Reject Leave Requests (FIFO & Pessimistic Lock)")
        UC_LeaveBal("Manage Leave Types & Adjust Annual Balances")
        UC_Reports("Generate Analytics & Export Sanitized CSV Reports")
        UC_Audit("Inspect Tamper-Evident Hash-Chained Audit Trail")
        UC_DSA("Execute DSA Laboratory & Algorithm Visualizers")
    end

    subgraph SelfServiceBoundary["Employee Self-Service (Employee)"]
        UC_EmpDash("View Employee Self-Service Dashboard")
        UC_OwnProfile("View Personal Employment Profile")
        UC_FileLeave("Submit Leave Application (Working-Day Calculation)")
        UC_CancelOwn("Withdraw Own Pending Leave Request")
        UC_OwnHistory("View Own Leave Balances, History & Notifications")
    end

    Admin --> UC_Login
    Admin --> UC_StepUp
    Admin --> UC_AdminDash
    Admin --> UC_ManageUsers
    Admin --> UC_ManageRBAC
    Admin --> UC_ProvisionAdmin
    Admin --> UC_SecEvents
    Admin --> UC_SysSettings
    Admin --> UC_EmpCRUD
    Admin --> UC_DeptPos
    Admin --> UC_LeaveReview
    Admin --> UC_LeaveBal
    Admin --> UC_Reports
    Admin --> UC_Audit
    Admin --> UC_DSA

    HR --> UC_Login
    HR --> UC_StepUp
    HR --> UC_HRDash
    HR --> UC_EmpCRUD
    HR --> UC_DeptPos
    HR --> UC_LeaveReview
    HR --> UC_LeaveBal
    HR --> UC_Reports
    HR --> UC_Audit
    HR --> UC_DSA

    Emp --> UC_Login
    Emp --> UC_EmpDash
    Emp --> UC_OwnProfile
    Emp --> UC_FileLeave
    Emp --> UC_CancelOwn
    Emp --> UC_OwnHistory
```

---

## 3. UML Class Diagram (Core Framework, Domain Use Cases & Persistence)

Reflects the actual classes in `Core\*`, `App\Controllers\*`, `App\Application\Leave\*`, `App\Domain\Leave\*`, `App\Services\*`, and `App\Models\*`.

```mermaid
classDiagram
    class Router {
        -array routes
        +get(string uri, string action, array middleware) void
        +post(string uri, string action, array middleware) void
        +dispatch(string uri, string method) void
    }

    class Session {
        +start() void
        +verifyAuthenticatedAccountState() bool
        +computeAuthFingerprint(array user) string
        +checkInactivityTimeout() void
    }

    class AuthorizationService {
        -PDO db
        -array rolePermissionCache
        +can(mixed userOrRole, string permission) bool
        +getPermissions(string roleSlug) array
        +updateRolePermissions(int actorUserId, string roleSlug, array permissionSlugs) array
    }

    class AuditService {
        -PDO db
        +record(array event) string
        +verifyChainIntegrity(int limit) array
        +sanitizeCsvField(string value) string
    }

    class LeaveController {
        -Leave leaveModel
        -LeaveControllerAdapter leaveAdapter
        +index() void
        +store() void
        +approve(int id) void
        +reject(int id) void
        +cancel(int id) void
        +processNextQueue() void
    }

    class LeaveControllerAdapter {
        -FileLeaveRequestUseCase fileUseCase
        -ApproveLeaveRequestUseCase approveUseCase
        -RejectLeaveRequestUseCase rejectUseCase
        -CancelLeaveRequestUseCase cancelUseCase
        +file(array input) LeaveOperationResult
        +approve(int requestId, int reviewerUserId) LeaveOperationResult
        +reject(int requestId, int reviewerUserId, string reason) LeaveOperationResult
        +cancel(int requestId, int actorEmployeeId, bool isHrOrAdmin, ?string reason) LeaveOperationResult
    }

    class ApproveLeaveRequestUseCase {
        -LeaveRequestRepositoryInterface requestRepo
        -LeaveBalanceRepositoryInterface balanceRepo
        -LeaveApprovalGuardInterface approvalGuard
        -TransactionManagerInterface txManager
        -OutboxRepositoryInterface outboxRepo
        +execute(ApproveLeaveRequestCommand command) LeaveOperationResult
    }

    class LeaveRequest {
        -int id
        -int employeeId
        -int leaveTypeId
        -LeaveDateRange dateRange
        -float numberOfDays
        -LeaveStatus status
        +approve(int reviewerUserId, DateTimeImmutable approvedAt) void
        +reject(int reviewerUserId, string reason, DateTimeImmutable rejectedAt) void
        +cancel(?string reason) void
    }

    class LeaveBalance {
        -int employeeId
        -int leaveTypeId
        -int year
        -float allocatedDays
        -float usedDays
        -float remainingDays
        +deduct(float days) void
        +restore(float days) void
    }

    class User {
        +create(array data) int
        +update(int id, array data) bool
        +findByEmail(string email) ?array
        +findByIdWithAuthState(int id) ?array
        +findByEmployeeId(int employeeId) ?array
        +updateRoleAndStatus(int id, string role, string status) bool
    }

    Router --> LeaveController : dispatches
    LeaveController --> LeaveControllerAdapter : delegates
    LeaveControllerAdapter --> ApproveLeaveRequestUseCase : invokes
    ApproveLeaveRequestUseCase --> LeaveRequest : mutates
    ApproveLeaveRequestUseCase --> LeaveBalance : deducts
    LeaveController --> AuditService : logs
    AuthorizationService --> User : authorizes
    Session --> User : verifies fingerprint
```

---

## 4. UML Sequence Diagrams

### 4.1 Portal Login, 2FA Email OTP & Session Fingerprint Initialization

```mermaid
sequenceDiagram
    autonumber
    actor User as User (Admin / HR / Employee)
    participant Auth as AuthController
    participant UserM as User Model (MySQL)
    participant OtpM as EmailOtp Model
    participant Mail as MailService (SMTP)
    participant Sess as Core\Session

    User->>Auth: POST /login (email, password, portal_role, csrf_token)
    Auth->>UserM: findByEmail(email)
    UserM-->>Auth: User record (id, role, status, password_hash)
    Auth->>Auth: Verify password_verify() & portal_role === user.role
    Auth->>OtpM: generateAndStoreOtp(userId) (CSPRNG 6-digit + HMAC-SHA256 Pepper)
    OtpM-->>Auth: Plaintext 6-digit OTP (stored as Bcrypt hash in email_otps)
    Auth->>Mail: sendOtpEmail(email, name, otpCode)
    Auth->>Sess: Set pending_otp_user_id & redirect('/verify-otp')
    User->>Auth: POST /verify-otp (otp_code, csrf_token)
    Auth->>OtpM: verifyOtp(userId, otp_code)
    OtpM-->>Auth: Valid (marks is_used = 1)
    Auth->>Sess:Session::regenerate() + Set user + computeAuthFingerprint()
    Auth-->>User: Redirect to /admin/dashboard, /hr/dashboard, or /employee/dashboard
```

### 4.2 Transaction-Safe Leave Approval with Pessimistic Locking & Outbox

```mermaid
sequenceDiagram
    autonumber
    actor Reviewer as HR Officer / Admin
    participant Ctrl as LeaveController
    participant Adapter as LeaveControllerAdapter
    participant UseCase as ApproveLeaveRequestUseCase
    participant DB as MySQL 8.0 InnoDB
    participant Outbox as realtime_outbox / WS Server

    Reviewer->>Ctrl: POST /leaves/{id}/approve (csrf_token)
    Ctrl->>Adapter: approve(requestId, reviewerUserId)
    Adapter->>UseCase: execute(ApproveLeaveRequestCommand)
    UseCase->>DB: BEGIN TRANSACTION
    UseCase->>DB: SELECT * FROM leave_requests WHERE id = :id FOR UPDATE
    DB-->>UseCase: Locked Pending Request (employee_id, leave_type_id, number_of_days)
    UseCase->>UseCase: Guard: reviewer.employee_id !== request.employee_id (No Self-Approval)
    UseCase->>DB: SELECT * FROM leave_balances WHERE employee_id = :emp AND leave_type_id = :lt AND year = :yr FOR UPDATE
    DB-->>UseCase: Locked LeaveBalance (remaining_days >= number_of_days)
    UseCase->>DB: UPDATE leave_balances SET used_days += days, remaining_days -= days
    UseCase->>DB: UPDATE leave_requests SET status = 'Approved', approved_by = :uid, approved_at = NOW()
    UseCase->>DB: INSERT INTO realtime_outbox (event_type='leave_request.approved', payload=JSON)
    UseCase->>DB: COMMIT
    UseCase-->>Ctrl: LeaveOperationResult(success: true)
    Ctrl->>DB: Record SHA-256 Hash-Chained Audit Log (LEAVE_APPROVED) & Enqueue Email Job
    Outbox->>DB: Poll unclaimed realtime_outbox rows
    Outbox-->>Reviewer: Push live WebSocket notification to Employee & HR/Admin channels
```

---

## 5. UML Activity Diagram — Complete Leave Request Lifecycle

```mermaid
flowchart TD
    Start(["Employee Initiates Leave Application"]) --> Input["Select Leave Type, Start Date, End Date & Reason"]
    Input --> Calc["LeaveCalculator Computes Business Days\n(Excludes Saturdays & Sundays)"]
    Calc --> ValidDates{"Start <= End &\nSame Year &\nDays > 0?"}
    ValidDates -- "No" --> RejectInput["Return Validation Error Flash"]
    ValidDates -- "Yes" --> CheckOverlap{"Overlaps Existing\nPending/Approved Request?"}
    CheckOverlap -- "Yes" --> RejectOverlap["Reject: Date Range Conflict"]
    CheckOverlap -- "No" --> CheckBal{"Remaining Balance >=\nRequested Days?"}
    CheckBal -- "No" --> RejectBal["Reject: Insufficient Leave Balance"]
    CheckBal -- "Yes" --> SavePending["Persist Leave Request (status = Pending)\n+ Write Transactional Outbox Event"]
    SavePending --> NotifyHR["Notify HR & Admin via WebSocket / In-App Notification"]
    NotifyHR --> Decision{"Next Action?"}

    Decision -- "Employee Cancels Pending" --> CancelPending["Update status = Cancelled\n(No Balance Deduction Needed)"]
    Decision -- "HR/Admin Rejects" --> RejectReq["Lock Row (FOR UPDATE)\nUpdate status = Rejected + Reason\nNotify Employee"]
    Decision -- "HR/Admin Approves" --> LockRows["BEGIN TX + Lock Request & Balance (FOR UPDATE)"]
    LockRows --> SoD{"Reviewer != Applicant&\nBalance Still Sufficient?"}
    SoD -- "No" --> Rollback["ROLLBACK & Return Error"]
    SoD -- "Yes" --> Deduct["Deduct Balance (used_days += N, remaining_days -= N)\nSet status = Approved\nCOMMIT + Outbox + Async Email"]
    Deduct --> PostApproved{"HR/Admin Reverses\nApproved Leave?"}
    PostApproved -- "Yes (With Mandatory Reason)" --> RestoreBal["BEGIN TX + Restore Balance (used_days -= N, remaining_days += N)\nSet status = Cancelled + Audit Log"]
    PostApproved -- "No" --> End(["Leave Lifecycle Complete"])
    CancelPending & RejectReq & RestoreBal & Rollback & RejectInput & RejectOverlap & RejectBal --> End
```

---

## 6. UML Component Diagram

```mermaid
flowchart TB
    subgraph Presentation["Presentation & Client Components"]
        Views["PHP View Templates\n(app/views/*)"]
        Skeleton["Skeleton Screen Engine\n(SkeletonRenderer + skeleton.js)"]
        WSClient["WebSocket Client\n(realtime.js)"]
    end

    subgraph CoreFramework["Core MVC & Security Components (core/)"]
        RouterComp["Core\\Router"]
        MiddlewareComp["Auth / Role / Permission / Csrf Middleware"]
        SessionComp["Core\\Session (Fingerprint Guard)"]
        RBACComp["Core\\AuthorizationService &\nCore\\PermissionRegistry"]
        AuditComp["Core\\AuditService\n(SHA-256 Hash Chain)"]
    end

    subgraph ApplicationDomain["Application & Domain Components (app/)"]
        WebCtrls["Web Controllers\n(app/controllers/*)"]
        ApiCtrls["REST API v1 Controllers\n(app/controllers/Api/*)"]
        LeaveUseCases["Clean Architecture Leave UseCases\n(app/Application/Leave/*)"]
        DomainServices["Domain & Security Services\n(StepUpAuth, Totp, LeaveCalculator, Mail)"]
        DsaLab["DSA Workbench\n(app/dsa/*)"]
    end

    subgraph AsyncInfra["Asynchronous & Real-Time Components"]
        WSServer["Ratchet WebSocket Daemon\n(app/realtime/Server.php)"]
        QueueWorkerComp["Background Queue Worker\n(app/Queue/QueueWorker.php)"]
        SchedulerComp["Console Scheduler Kernel\n(app/Console/Kernel.php)"]
    end

    subgraph Persistence["Data Persistence Components"]
        ModelsComp["Models & MySql Repositories"]
        MySQLDB[("MySQL 8.0 InnoDB\n(18 Tables)")]
    end

    Views & Skeleton & WSClient --> RouterComp
    RouterComp --> MiddlewareComp
    MiddlewareComp --> SessionComp & RBACComp
    MiddlewareComp --> WebCtrls & ApiCtrls
    WebCtrls & ApiCtrls --> LeaveUseCases & DomainServices & DsaLab & AuditComp
    LeaveUseCases & DomainServices & AuditComp --> ModelsComp
    WSServer & QueueWorkerComp & SchedulerComp --> ModelsComp
    ModelsComp --> MySQLDB
```

---

## 7. UML Deployment Diagram

Reflects the verified multi-container Docker Compose topology (`docker-compose.yml`) and production Linux host deployment.

```mermaid
flowchart TB
    subgraph ClientNode["Client Workstation / Mobile Device"]
        BrowserNode["Web Browser (Brave / Chrome / Edge)\nHTTPS (:443 / :8080) & WebSocket (/ws)"]
    end

    subgraph DockerHost["Docker Host / Ubuntu 24.04 LTS Server (hris_network Bridge)"]
        subgraph EdgeContainer["Container: hris_nginx (nginx:1.31.6-alpine)"]
            NginxProc["Nginx Reverse Proxy\nPort 8080 -> app:80 & realtime:8090\nPort 8082 -> /stub_status"]
        end

        subgraph AppContainer["Container: hris_app (hris-system-app:latest)"]
            ApachePHP["Apache 2.4 + PHP 8.5.12\nDocumentRoot: /var/www/html/public"]
        end

        subgraph RealtimeContainer["Container: hris_realtime (hris-system-app:latest)"]
            RatchetDaemon["php bin/realtime-server.php\nPort 8090 (www-data)"]
        end

        subgraph WorkerContainer["Container: hris_worker (hris-system-app:latest)"]
            WorkerDaemon["php bin/queue-worker.php\n--sleep=2 --lease=300"]
        end

        subgraph SchedulerContainer["Container: hris_scheduler"]
            CronDaemon["Cron Daemon -> php bin/console schedule:run"]
        end

        subgraph DBContainer["Container: hris_db (mysql:8.0.46)"]
            MySQLServer[("MySQL 8.0 InnoDB (:3306 / Host :3307)\nVolume: hris_db_data")]
        end

        subgraph SupportContainers["Observability & Utility Containers"]
            MailpitNode["hris_mailpit (axllent/mailpit:v1.22)\nSMTP :1025 | UI :8025"]
            PromNode["hris_prometheus (prom/prometheus:v2.51.0)\nPort :9090 | Volume: hris_prometheus_data"]
            GrafanaNode["hris_grafana (grafana/grafana:10.4.1)\nPort :3100 | Volume: hris_grafana_data"]
            ExportersNode["hris_nginx_exporter (:9113) &\nhris_mysqld_exporter (:9104)"]
            SwaggerNode["hris_swagger_ui (:8083)"]
            PMANode["hris_phpmyadmin (:8081)"]
        end
    end

    BrowserNode -- "HTTP :8080 / WS /ws" --> NginxProc
    NginxProc -- "HTTP :80" --> ApachePHP
    NginxProc -- "WS :8090" --> RatchetDaemon
    ApachePHP & RatchetDaemon & WorkerDaemon & SchedulerContainer --> MySQLServer
    ApachePHP & WorkerDaemon --> MailpitNode
    ExportersNode --> NginxProc & MySQLServer
    PromNode --> ExportersNode
    GrafanaNode --> PromNode
```

