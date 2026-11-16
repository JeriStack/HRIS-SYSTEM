# NEXORA HR — Academic & Technical Project Defense Guide

**Project Title:** NEXORA HR — Enterprise Human Resource Information System & Leave Management Platform  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.5 — Project Defense Preparation Guide

---

## 1. Project Introduction & Academic Context

### 1.1 Background
Organizations managing growing workforces frequently rely on fragmented spreadsheets, manual paper forms, or disconnected tools to track employee records, organizational hierarchies, and leave balances. These manual workflows lead to calculation errors (such as counting weekends as leave days), concurrent approval race conditions (two managers approving conflicting requests simultaneously), lack of real-time visibility, and unverifiable audit trails.

### 1.2 Problem Statement
1. **Data Fragmentation & Inconsistency:** Employee profiles, department/position assignments, and user login states easily drift out of sync without relational enforcement.
2. **Concurrency & Leave Overdraft Risks:** When multiple leave requests are reviewed concurrently without database-level row locking, an employee's leave quota can be overdrawn or double-deducted.
3. **Inadequate Access Control & Auditability:** Legacy two-role or flat permission models fail to separate system governance (Administrator) from daily workforce operations (HR Officer) and employee self-service (Employee), and lack tamper-evident logging.
4. **Algorithmic Opacity in Academic Systems:** Academic HRIS projects rarely demonstrate how core Computer Science data structures and algorithms (`Queue`, `HashTable`, `BinarySearchTree`, `MergeSort`, `BinarySearch`) operate on real business records with measurable complexity telemetry.

### 1.3 Project Objectives
1. **Design and implement a centralized, relational HRIS** using native PHP 8.5 and MySQL 8.0 (InnoDB) enforcing strict referential integrity across 18 tables.
2. **Enforce a Three-Role Dynamic RBAC Security Architecture** (`admin`, `hr`, `employee`) with CLI-bootstrapped Administrator provisioning, mandatory 2FA (Email OTP & TOTP), Step-Up Authentication, and live session fingerprinting.
3. **Guarantee ACID Transactional Leave Processing** with automatic Monday–Friday business-day calculation, overlap prevention, segregation of duties, and pessimistic row locking (`SELECT ... FOR UPDATE`).
4. **Provide Real-Time & Asynchronous Event Processing** via a Transactional Outbox, Ratchet WebSocket server (`:8090`), MySQL background job worker, and Cron scheduler.
5. **Integrate an Interactive Data Structures & Algorithms (DSA) Workbench** that applies custom-built data structures and sorting/searching algorithms to HRIS workflows and visualizes their execution traces.

### 1.4 Scope and Limitations
- **Included Scope:**
  - 3-portal authentication (`Admin Portal`, `HR Portal`, `Employee Portal`) with 2FA Email OTP and RFC 6238 TOTP.
  - Dynamic database-driven Role & Permission Matrix (`roles`, `permissions`, `role_permissions`) with immutable Admin protection.
  - Full Employee, Department, and Position lifecycle management.
  - End-to-end Leave Management (filing, business-day calculation, FIFO approval queue, approval, rejection, cancellation/refund, monthly accrual, year-end carry-over).
  - Tamper-evident SHA-256 hash-chained Audit Trail (`activity_logs`) and formula-sanitized CSV reporting.
  - Real-time WebSocket notifications (`/ws`), background job queue (`jobs`/`failed_jobs`), REST API v1 with OpenAPI/Swagger UI, Prometheus/Grafana monitoring, and Skeleton Screen loading system.
- **Explicit Limitations:**
  - Payroll tax computation, biometric hardware clock-in integration, and third-party applicant tracking (ATS) are outside the current scope.
  - Statutory public holidays vary by jurisdiction; `LeaveCalculator` currently excludes Saturdays and Sundays as statutory non-working days.

### 1.5 Target Users & Significance
- **Administrators (`admin`):** IT & Security governance officers responsible for user provisioning, RBAC policies, security event monitoring, and system health.
- **HR Officers (`hr`):** Human resource personnel managing employee onboarding, organizational structure, leave approvals, and workforce analytics.
- **Employees (`employee`):** Workforce members accessing personal profiles, leave balances, leave applications, and real-time status notifications.

---

## 2. Deep Technical Explanations for the Defense Panel

### 2.1 System Architecture & Technology Stack
- **Custom 4-Tier MVC + Clean Architecture Domain Layer:**
  - **Presentation Tier:** Server-side rendered PHP views (`app/views/`) enhanced with progressive vanilla ES6+ modules (`skeleton.js`, `realtime.js`, `app.js`).
  - **Routing & Middleware Tier (`core/`):** Front controller (`public/index.php`) dispatching through fail-closed `AuthMiddleware`, `RoleMiddleware`, `PermissionMiddleware`, and `CsrfMiddleware`.
  - **Application & Domain Tier (`app/Application/Leave/`, `app/Domain/Leave/`, `app/services/`):** Encapsulates leave business rules in framework-independent use cases (`FileLeaveRequestUseCase`, `ApproveLeaveRequestUseCase`, `RejectLeaveRequestUseCase`, `CancelLeaveRequestUseCase`).
  - **Persistence & Infrastructure Tier (`app/models/`, `app/Infrastructure/`):** MySQL 8.0 InnoDB accessed exclusively via PDO prepared statements (`ATTR_EMULATE_PREPARES => false`).

### 2.2 Database Design (18 InnoDB Tables)
- Organized into four domains:
  1. **Workforce & Org Structure:** `departments`, `positions`, `employees`.
  2. **Identity, RBAC & MFA:** `roles`, `permissions`, `role_permissions`, `users`, `email_otps`, `user_totp`.
  3. **Leave Engine:** `leave_types`, `leave_balances`, `leave_requests`.
  4. **Audit, Notifications & Async Infrastructure:** `activity_logs`, `notifications`, `realtime_tickets`, `realtime_outbox`, `jobs`, `failed_jobs`.

### 2.3 Authentication, MFA & Continuous Session Verification
- **Two-Stage Sign-In:** Primary password verification (`password_verify()` against bcrypt cost 12) + portal role verification, followed by a 6-digit CSPRNG Email OTP hashed with an HMAC-SHA256 server pepper (`OTP_PEPPER_SECRET`) and bcrypt (`EmailOtp::hashOtp()`).
- **Live Session Fingerprinting (`Session::verifyAuthenticatedAccountState()`):** Every authenticated HTTP request recomputes `hash('sha256', id|role|status|employment_status|password_hash)` against the database. If an admin deactivates a user, changes their role, or resets their password, the user's session is terminated on their very next click.

### 2.4 Three-Role Dynamic RBAC & Admin Safeguards
- **Authoritative Registry (`Core\PermissionRegistry`):** Defines 3 system roles (`admin`, `hr`, `employee`) and 28 canonical permission slugs with legacy capability aliases.
- **Immutable Admin Authority:** `AuthorizationService::updateRolePermissions()` throws an `InvalidArgumentException` if any caller attempts to modify the `admin` role's permissions.
- **Lockout Prevention:** `User::countActiveAdmins()` and `User::countActiveHr()` prevent deactivating or demoting the last active Administrator or HR Officer, and block self-demotion/self-deactivation.

### 2.5 Leave Management & Pessimistic Concurrency Control
- **Why `SELECT ... FOR UPDATE`?** Without row locking, two HR officers clicking "Approve" at the same millisecond could both read `remaining_days = 5`, approve two separate 4-day requests, and overwrite each other's update. By wrapping the operation in `BEGIN TRANSACTION` and locking both the `leave_requests` row and the `leave_balances` row (`SELECT ... FOR UPDATE`), MySQL serializes concurrent approvals and guarantees `remaining_days >= 0`.
- **Segregation of Duties:** Both `MySqlLeaveApprovalGuard` and `Leave::approveRequest()` verify that the reviewer's `employee_id` does not match the applicant's `employee_id`, preventing an HR officer from approving their own leave.

### 2.6 Tamper-Evident Audit Trail (`Core\AuditService`)
- Every audit entry in `activity_logs` stores `prev_hash` (the `event_hash` of the preceding row, or `GENESIS_000...` for row 1) and computes `event_hash = SHA-256(canonical_fields | prev_hash)`.
- If an attacker alters a historical log row directly in MySQL, `AuditService::verifyChainIntegrity()` detects the broken cryptographic link immediately.

### 2.7 Real-Time WebSockets, Transactional Outbox & Background Queue
- **Transactional Outbox (`realtime_outbox`):** Instead of pushing to WebSockets inside an HTTP controller (which could fail or broadcast uncommitted data if a transaction rolls back), domain events are inserted into `realtime_outbox` inside the same MySQL transaction. The Ratchet WebSocket server (`bin/realtime-server.php`) polls committed outbox events and pushes them to authorized browser channels.
- **Single-Use WebSocket Tickets (`realtime_tickets`):** Browsers never send session IDs in WebSocket query strings; they request a 30-second single-use CSPRNG ticket via `POST /realtime/ticket`, which is hashed with SHA-256 and consumed atomically on handshake.

### 2.8 DSA Academic Component
- Implements 12 custom classes (`ArrayStructure`, `LinkedList`, `Stack`, `Queue`, `HashTable`, `BinarySearchTree`, `LinearSearch`, `BinarySearch`, `BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, `QuickSort`, `RecursionHelper`) with zero reliance on built-in PHP `sort()`/`array_search()` inside algorithm bodies.
- Directly powers the **FIFO Leave Approval Queue** (`LeaveApprovalQueueService`), **$O(1)$ Employee Hash Lookup**, and **Interactive DSA Laboratory (`/dsa`)**.

