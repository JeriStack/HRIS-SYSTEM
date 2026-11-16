# NEXORA HR — Final Defense Presentation Deck (22-Slide Master Outline & Speaker Notes)

**Project:** NEXORA HR — Human Resource Information System  
**Brand Identity:** Cobalt Blue (`#2563EB`) & Deep Slate (`#0F172A`) Enterprise Theme  
**Tagline:** *People. Processes. Progress.*  
**Phase:** 16.6 — Final Presentation Deliverable

---

## Slide 1: Title Slide — NEXORA HR
- **Visual Layout:** Dark Slate hero background (`public/assets/img/nexora-landing-bg.jpg`), NEXORA HR full emblem (`nexora-logo-full.png`), bold title typography.
- **Slide Bullets:**
  - **NEXORA HR** — Enterprise Human Resource Information System & Leave Management Platform
  - *Tagline:* **People. Processes. Progress.**
  - Built with Native PHP 8.5, MySQL 8.0 (InnoDB), Clean Architecture, Ratchet WebSockets & Custom DSA Engine
- **Speaker Notes:**
  > "Good day to the defense panel. Today we present **NEXORA HR — People. Processes. Progress.**, an enterprise-grade Human Resource Information System and Leave Management Platform engineered from the ground up using PHP 8.5, MySQL 8.0 InnoDB, a three-role dynamic RBAC security model, transaction-safe pessimistic locking, real-time WebSockets, and a custom Data Structures and Algorithms workbench."

---

## Slide 2: Project Background
- **Visual Layout:** Two-column comparison (Legacy Manual HR Processes vs. Modern Centralized HRIS).
- **Slide Bullets:**
  - Growing organizations struggle with disconnected spreadsheets for employee records and leave quotas.
  - Manual leave day counting frequently miscalculates weekends and cross-year date ranges.
  - Lack of real-time communication delays leave approvals and workforce scheduling.
  - Modern governance requires strict separation between IT System Administration, HR Operations, and Employee Self-Service.
- **Speaker Notes:**
  > "We began this project by analyzing why traditional HR workflows fail at scale: disconnected employee records, manual date math errors, and the absence of strict role separation and real-time notifications."

---

## Slide 3: Problem Statement
- **Visual Layout:** 4-Card Problem Matrix with warning icons.
- **Slide Bullets:**
  1. **Data Inconsistency:** Unlinked employee, department, position, and login records drift out of sync.
  2. **Concurrency Race Conditions:** Simultaneous leave approvals without row-level locking can overdraft leave balances.
  3. **Access Control & Audit Gaps:** Flat role models allow privilege creep and lack tamper-evident audit trails.
  4. **Algorithmic Opacity:** Academic HR systems rarely demonstrate measurable Computer Science data structures and algorithms on real workforce data.
- **Speaker Notes:**
  > "Specifically, NEXORA HR addresses four core engineering problems: relational data drift, concurrent leave balance race conditions, access-control/auditability gaps, and the integration of verifiable Computer Science data structures and algorithms."

---

## Slide 4: Project Objectives
- **Visual Layout:** Numbered milestone pillars (`01` to `05`).
- **Slide Bullets:**
  1. **Relational Integrity:** Architect an 18-table MySQL 8.0 InnoDB schema with strict foreign keys and composite indexes.
  2. **Three-Role Dynamic RBAC & MFA:** Enforce `admin`, `hr`, and `employee` boundaries with mandatory 2FA (Email OTP & TOTP) and live session fingerprinting.
  3. **Transaction-Safe Leave Engine:** Implement Monday–Friday working-day calculation, overlap guards, and `SELECT ... FOR UPDATE` row locking.
  4. **Real-Time & Async Infrastructure:** Deliver sub-100ms WebSocket notifications via a Transactional Outbox and MySQL Queue Worker.
  5. **Interactive DSA Workbench:** Build and benchmark 12 custom data structures and algorithms integrated with HRIS workflows.
- **Speaker Notes:**
  > "Every objective listed here has been fully implemented and verified by our automated test suite of 669 PHPUnit tests."

---

## Slide 5: Scope and Limitations
- **Visual Layout:** Split panel (`Implemented & Verified Scope` vs. `Explicit Boundaries`).
- **Slide Bullets:**
  - **Implemented & Verified Scope:**
    - 3-Portal Authentication (`Admin`, `HR`, `Employee`), 2FA Email OTP (HMAC-SHA256 peppered) & AES-256-GCM TOTP
    - Dynamic RBAC Matrix, CLI Admin Bootstrap (`bin/create_admin_user.php`), Step-Up Authentication
    - Employee, Department & Position CRUD with automatic leave balance initialization
    - End-to-End Leave Lifecycle (File, FIFO Queue, Approve, Reject, Cancel/Refund, Accrual, Carry-Over)
    - SHA-256 Hash-Chained Audit Trail, Sanitized CSV Exports, REST API v1 (OpenAPI 3.0) & Skeleton Screen System
  - **Explicit Limitations:**
    - Excludes payroll tax disbursement, hardware biometric attendance terminals, and external job-board ATS.
- **Speaker Notes:**
  > "We clearly define our boundaries: NEXORA HR covers the complete core HRIS, access governance, leave lifecycle, audit trail, real-time notifications, REST API v1, and DSA workbench, while excluding payroll disbursement and biometric hardware."

---

## Slide 6: System Users & Three-Role RBAC Model
- **Visual Layout:** 3 Portal Cards (`docs/screenshots/admin/admin-portal-login.png`, `docs/screenshots/hr/hr-portal-login.png`, `docs/screenshots/employee/employee-portal-login.png`).
- **Slide Bullets:**
  - **🛡️ Administrator (`admin`):** Full system governance, user provisioning, dynamic role-permission matrix (`/admin/roles`), security events, system health, and audit oversight. Immutable system-protected permissions.
  - **💼 HR Officer (`hr`):** Workforce directory CRUD, department/position management, FIFO leave approval queue, balance adjustments, and CSV analytics. Blocked from Admin governance.
  - **👤 Employee (`employee`):** Self-service profile, leave balance inspection, leave filing, pending request withdrawal, and real-time notifications. Strict IDOR ownership isolation.
- **Speaker Notes:**
  > "Here you see actual screenshots of our three role-segregated login portals. Server-side middleware (`RoleMiddleware`, `PermissionMiddleware`, `AuthorizationService`) enforces fail-closed authorization on every HTTP route, API endpoint, and WebSocket channel."

---

## Slide 7: Technology Stack & Runtime Environment
- **Visual Layout:** Layered stack grid with verified version badges.
- **Slide Bullets:**
  - **Backend & Domain:** Native PHP `8.5.12` (Custom 4-Tier MVC + Clean Architecture Use Cases)
  - **Database:** MySQL `8.0.46` (`InnoDB`, `utf8mb4_unicode_ci`, ACID Transactions, Row Locks)
  - **Real-Time & Async:** Ratchet WebSocket Daemon (`:8090`), MySQL Queue Worker (`jobs`/`failed_jobs`), Cron Scheduler
  - **Frontend UI:** Semantic HTML5, Custom Design System CSS (Light/Dark Mode), Vanilla ES6+, Accessible Skeleton Screen System
  - **DevOps & Observability:** Docker Compose (10 containers), Nginx `1.31.6-alpine`, Prometheus (`:9090`), Grafana (`:3100`), Swagger UI (`:8083`), Mailpit (`:8025`)
  - **Quality Assurance:** PHPUnit `13.4.5` (669 tests), PHPStan `2.3`, PHP_CodeSniffer `4.0` (PSR-12), Selenium E2E (Brave/Chromium)
- **Speaker Notes:**
  > "Every version shown on this slide is verified in our repository and CI pipeline."

---

## Slide 8: System Architecture
- **Visual Layout:** High-resolution Mermaid System Architecture Diagram from `docs/02-technical/architecture/system-architecture.md`.
- **Slide Bullets:**
  - **Edge Tier:** Nginx reverse proxy (`:8080`) routes HTTP requests to Apache/PHP and upgrades `/ws` connections to Ratchet (`:8090`).
  - **Middleware Pipeline:** Sequential `AuthMiddleware` -> `RoleMiddleware` -> `PermissionMiddleware` -> `CsrfMiddleware`.
  - **Clean Architecture Core:** Controllers delegate leave operations through `LeaveControllerAdapter` to isolated Use Cases.
  - **Transactional Outbox & Queue:** Business state changes, audit logs, and outbox events commit atomically in MySQL.
- **Speaker Notes:**
  > "Our architecture decouples synchronous HTTP transactions from real-time WebSocket broadcasts and SMTP email delivery using the Transactional Outbox pattern and a MySQL-backed job queue."

---

## Slide 9: Database Design & 18-Table ERD
- **Visual Layout:** Entity Relationship Diagram from `docs/02-technical/database/erd.mmd`.
- **Slide Bullets:**
  - **18 InnoDB Tables** across 4 domains:
    - *Workforce:* `departments`, `positions`, `employees`
    - *Identity & RBAC:* `roles`, `permissions`, `role_permissions`, `users`, `email_otps`, `user_totp`
    - *Leave Engine:* `leave_types`, `leave_balances`, `leave_requests`
    - *Audit & Async:* `activity_logs`, `notifications`, `realtime_tickets`, `realtime_outbox`, `jobs`, `failed_jobs`
  - Enforces foreign keys (`RESTRICT`, `CASCADE`, `SET NULL`), unique constraints, and targeted composite indexes.
- **Speaker Notes:**
  > "Our ERD reflects all 18 actual InnoDB tables in `database/schema.sql`. Notice the composite unique constraint `uq_balances_emp_type_year (employee_id, leave_type_id, year)` which allows row-level locking on individual leave balances."

---

## Slide 10: Core System Workflows
- **Visual Layout:** End-to-End Workflow Diagram connecting Admin Provisioning -> HR Onboarding -> Employee Leave Filing -> HR Approval -> Real-Time Notification & Audit Log.
- **Slide Bullets:**
  - **Unified Lifecycle:** Every action flows through input validation, RBAC authorization, transactional persistence, tamper-evident audit logging, and real-time notification dispatch.
  - Zero fabricated or placeholder states; asynchronous views transition smoothly via the Skeleton Screen system.
- **Speaker Notes:**
  > "All system workflows are reconciled with our live codebase and documented with flowcharts in `docs/02-technical/workflows/system-workflows.md`."

---

## Slide 11: Employee, Department & Position Management
- **Visual Layout:** Workflow diagram + Employee Directory & Profile fields summary.
- **Slide Bullets:**
  - **Validation Guardrails (`EmployeeValidator`):** Enforces unique `employee_no` and `email`, valid hire/birth dates, and verifies that the assigned `position_id` belongs to the selected `department_id`.
  - **Automated Onboarding:** Creating an employee automatically initializes annual `leave_balances` across all configured `leave_types` and optionally provisions a linked `users` account.
  - **Referential Protection:** Departments and positions with assigned personnel cannot be accidentally deleted.
- **Speaker Notes:**
  > "When HR onboards a new employee, NEXORA HR validates organizational hierarchy alignment and automatically provisions their initial leave balances."

---

## Slide 12: Transaction-Safe Leave Management Engine
- **Visual Layout:** Sequence Diagram of `ApproveLeaveRequestUseCase` (`SELECT ... FOR UPDATE`).
- **Slide Bullets:**
  - **Working-Day Calculator (`LeaveCalculator`):** Automatically counts Monday–Friday business days and rejects weekend-only or cross-year ranges.
  - **Overlap Guard (`MySqlLeaveFilingGuard`):** Blocks filing if an employee has an overlapping `Pending` or `Approved` request.
  - **Pessimistic Row Locking:** `ApproveLeaveRequestUseCase` locks both `leave_requests` and `leave_balances` with `SELECT ... FOR UPDATE` inside a single ACID transaction.
  - **Segregation of Duties:** Reviewers are strictly blocked from approving or rejecting their own leave requests.
- **Speaker Notes:**
  > "This is one of the strongest technical pillars of NEXORA HR: pessimistic row locking guarantees that concurrent approvals can never cause a negative leave balance or race condition."

---

## Slide 13: Authentication, MFA & Continuous Session Security
- **Visual Layout:** Authentication & Session Fingerprint Verification Flowchart.
- **Slide Bullets:**
  - **Mandatory 2FA:** 6-digit CSPRNG Email OTP hashed with HMAC-SHA256 (`OTP_PEPPER_SECRET`) + Bcrypt (`cost = 12`), plus RFC 6238 TOTP (`AES-256-GCM`).
  - **Continuous Live Verification (`Session::verifyAuthenticatedAccountState()`):** Recomputes SHA-256 `auth_fingerprint` (`id|role|status|employment_status|password_hash`) on every HTTP request.
  - **Step-Up Authentication (`StepUpAuthService`):** Requires recent MFA (15m window) or password re-confirmation before role changes, user deactivation, or admin provisioning.
- **Speaker Notes:**
  > "If an administrator deactivates a user or changes their role, that user doesn't stay logged in until their cookie expires—their `auth_fingerprint` invalidates on their very next request and their WebSocket tickets are revoked immediately."

---

## Slide 14: Tamper-Evident Audit Trail & Real-Time Notifications
- **Visual Layout:** Diagram of SHA-256 Hash Chain (`prev_hash` -> `event_hash`) + Transactional Outbox to WebSocket (`/ws`).
- **Slide Bullets:**
  - **Cryptographic Audit Chain (`Core\AuditService`):** Every `activity_logs` record chains the prior record's `event_hash` into its own SHA-256 digest and automatically redacts sensitive keys (`password`, `otp`, `token`, `secret`).
  - **Transactional Outbox (`realtime_outbox`):** Committed domain events are polled by the Ratchet WebSocket server (`:8090`) and pushed to authorized `user.{id}`, `role.admin`, and `role.hr` channels.
  - **Background Queue (`QueueWorker`):** Dispatches transactional emails (`SendEmailHandler`) and CSV exports asynchronously with exponential backoff.
- **Speaker Notes:**
  > "Our audit trail is cryptographically verifiable via `AuditService::verifyChainIntegrity()`, and our real-time notifications use single-use 30-second WebSocket handshake tickets."

---

## Slide 15: Supplementary DSA Module & Complexity Analysis
- **Visual Layout:** Empirical Benchmark Charts (`docs/screenshots/dsa/chart_search_execution_time.svg` & `chart_sorting_execution_time.svg`) + Complexity Table.
- **Slide Bullets:**
  - **12 Custom Implementations (`app/dsa/`):**
    - *Structures:* `ArrayStructure`, `LinkedList`, `Stack` (LIFO), `Queue` (FIFO), `HashTable` (Polynomial Chaining), `BinarySearchTree`
    - *Algorithms:* `LinearSearch`, `BinarySearch`, `BubbleSort`, `SelectionSort`, `InsertionSort`, `MergeSort`, `QuickSort`, `RecursionHelper`
  - **Practical HRIS Integration:** Powers the **FIFO Leave Approval Queue** (`LeaveApprovalQueueService`), **$O(1)$ Employee Hash Lookup**, and the interactive `/dsa` visualizer with live step traces (`DsaTraceService`).
- **Speaker Notes:**
  > "All 12 data structures and algorithms are written from scratch without delegating to PHP's native `sort()` or `array_search()`, and are integrated into both our HRIS services and the `/dsa` workbench."

---

## Slide 16: Testing & Quality Assurance Results
- **Visual Layout:** Verified Test Metrics Dashboard Table from `docs/04-quality-assurance/test-plan-and-results.md`.
- **Slide Bullets:**
  - **Total Automated PHPUnit Tests:** **669 Tests** (`2,528` Assertions) — **0 Failures, 0 Errors**
  - **Static Analysis (PHPStan 2.3):** **PASS (`[OK] No errors`)**
  - **Coding Standards (PSR-12 Baseline):** **PASS (`0` new violations)**
  - **Full-System Functional Audit:** Identified, root-caused, fixed, and regression-tested 7 system defects (`BUG-001` through `BUG-007` in `SystemAuditFixesTest.php`).
- **Speaker Notes:**
  > "We didn't stop at happy-path testing. We conducted a full-system functional audit, remediated 7 edge-case defects across credential updates, async email queuing, and RBAC parity, and verified all 669 tests pass with zero errors."

---

## Slide 17: Empirical Performance Evaluation
- **Visual Layout:** Latency Percentile Table (`avg`, `p50`, `p95`, `ops/sec`) from `docs/04-quality-assurance/performance-test-plan-and-results.md`.
- **Slide Bullets:**
  - **RBAC Evaluation (`AuthorizationService::can`):** `0.0036 ms` median (`55,085 ops/sec`)
  - **Leave Working-Day Calculation:** `0.0313 ms` median (`23,612 ops/sec`)
  - **Skeleton Table Rendering (SSR):** `1.0936 ms` median (`887 ops/sec`)
  - **Search ($N=1,000$):** `HashTable` `0.0009 ms` ($O(1)$) vs. `BinarySearch` `0.0529 ms` ($O(\log n)$) vs. `LinearSearch` `0.9578 ms` ($O(n)$)
  - **Sorting ($N=1,000$):** `MergeSort` `9.68 ms` & `QuickSort` `10.92 ms` ($O(n \log n)$)
  - **Peak Memory Footprint:** `4.00 MB` (Benchmark CLI) / `48.00 MB` (Full 669-test suite)
- **Speaker Notes:**
  > "These measurements were captured using PHP 8.5's nanosecond `hrtime(true)` clock. Notice how `HashTable` lookup at 0.9 microseconds and `BinarySearch` at 52.9 microseconds empirically validate our theoretical complexity analysis."

---

## Slide 18: Installation, Deployment & Observability
- **Visual Layout:** UML Deployment Diagram (`docker-compose.yml` 10-container topology).
- **Slide Bullets:**
  - **One-Command Container Stack (`docker compose up -d --build`):** Orchestrates `nginx`, `app`, `realtime`, `worker`, `scheduler`, `db`, `phpmyadmin`, `prometheus`, `grafana`, `swagger-ui`, and `mailpit`.
  - **Automated CI/CD (`.github/workflows/ci.yml`):** Validates `actionlint`, PHP 8.5 syntax, PSR-12 baseline, PHPStan, MySQL 8.0 migrations & PHPUnit, Terraform (`tflint`), and Docker builds (`hadolint`).
  - **18-Point Production Readiness Checklist:** Verified `PASS` across all 18 security, backup, migration, and lockout safeguards.
- **Speaker Notes:**
  > "Our deployment architecture is fully containerized and backed by an automated 4-job GitHub Actions CI pipeline and Prometheus/Grafana observability."

---

## Slide 19: Live System Demonstration Overview
- **Visual Layout:** 4-Step Demo Flow Banner (Admin Governance -> HR Workforce & FIFO Queue -> Employee Self-Service -> Audit, Reports & DSA Lab).
- **Slide Bullets:**
  1. **Admin Portal:** Sign-In + 2FA OTP -> Governance Dashboard -> Dynamic 3-Role Permission Matrix (`/admin/roles`).
  2. **Employee Portal:** Sign-In -> Submit Leave Application (`/leaves/create`) with live working-day calculation.
  3. **HR Portal:** Real-Time WebSocket Toast -> FIFO Queue / Pessimistic-Locked Approval (`POST /leaves/{id}/approve`).
  4. **Verification:** Employee updated balance -> SHA-256 Audit Trail (`/activity-logs`) -> CSV Export -> DSA Lab (`/dsa`).
- **Speaker Notes:**
  > "We will now transition to the live demonstration following our 15-step verification script."

---

## Slide 20: Conclusion
- **Visual Layout:** Summary achievement pillars with NEXORA HR branding.
- **Slide Bullets:**
  - **NEXORA HR** successfully unifies enterprise HRIS operations, strict 3-role dynamic RBAC governance, ACID transactional leave accounting, real-time WebSocket notifications, and an interactive Computer Science DSA workbench.
  - Verified by **669 passing automated tests**, **0 PHPStan errors**, **0 new PSR-12 violations**, and complete technical, operational, and user documentation.
- **Speaker Notes:**
  > "In conclusion, NEXORA HR delivers a secure, transaction-safe, and thoroughly verified HRIS platform that meets both enterprise software engineering standards and rigorous Computer Science academic requirements."

---

## Slide 21: Recommendations & Future Improvements
- **Visual Layout:** Roadmap cards (`Future Phase Enhancements`).
- **Slide Bullets:**
  1. **Configurable Holiday Calendar Table:** Extend `LeaveCalculator` with a database-backed `public_holidays` table for regional statutory holiday exclusion.
  2. **Runtime-Mutable System Settings Table:** Persist Admin general/notification toggles in a dedicated `system_settings` table across container restarts.
  3. **Self-Balancing AVL / Red-Black Tree Option:** Extend `BinarySearchTree` in the DSA module with automatic rotations to guarantee $O(\log n)$ worst-case height on pre-sorted inputs.
  4. **Multi-Level Departmental Approval Workflows:** Support optional department-head pre-approval before final HR sign-off.
- **Speaker Notes:**
  > "Looking ahead, the clean architectural boundaries we established make it straightforward to add regional holiday calendars, self-balancing trees, and multi-stage approval chains."

---

## Slide 22: Questions and Answers
- **Visual Layout:** Clean centered NEXORA HR emblem, tagline *"People. Processes. Progress."*, and quick-links to live application, Swagger UI, and documentation hub.
- **Slide Bullets:**
  - **Thank You!**
  - **NEXORA HR — People. Processes. Progress.**
  - We welcome questions from the defense panel.
- **Speaker Notes:**
  > "Thank you for your time and attention. We are now ready to answer your questions and demonstrate any part of the codebase, database, or live application."

