# Phase 14 — System-Wide Quality Assurance Test Plan
## HRIS-SYSTEM | Employee HRIS and Leave Management System

- **Application:** HRIS-SYSTEM
- **Phase:** Phase 14 — Testing and Quality Assurance
- **Document Version:** 1.0.0
- **AI Development Environment:** Antigravity IDE
- **Target Environments:** Native PHP 8.5.12, Docker Compose (Nginx, PHP-FPM, MySQL 8.0, Ratchet WebSocket)
- **QA Lead:** Senior Software Architect & Quality Assurance Specialist
- **Status:** APPROVED & EXECUTED

---

## 1. Executive Summary & Purpose

The purpose of this Quality Assurance Plan is to establish a rigorous, evidence-based verification framework for the integrated HRIS-SYSTEM. The application is an enterprise-grade Human Resource Information and Leave Management System powered by native PHP, MySQL, a Ratchet-based WebSocket real-time daemon, and a custom Data Structures and Algorithms (DSA) laboratory.

This plan details the testing methodology, scope, test environment specifications, role-based security boundaries, defect classification criteria, and exit criteria required for production release.

---

## 2. Scope of Testing

Testing covers the entire integrated application lifecycle across 8 core quality domains:

```
+---------------------------------------------------------------------------------------+
|                               PHASE 14 QA SCOPE MATRIX                               |
+---------------------------------------------------------------------------------------+
| 1. Functional Testing   | Auth, OTP, Employee CRUD, Departments, Positions, Leaves,   |
|                         | Balances, History, Queues, Notifications, Reports           |
+-------------------------+-------------------------------------------------------------+
| 2. DSA Testing          | Linear & Binary Search, 5 Sorts (Bubble, Selection,         |
|                         | Insertion, Merge, Quick), Stack, Queue, Linked List,        |
|                         | Hash Table, Binary Search Tree, Recursion Helpers           |
+-------------------------+-------------------------------------------------------------+
| 3. Security Testing     | RBAC (HR vs Employee), IDOR Prevention, CSRF Protection,    |
|                         | Session Fixation, Inactivity Timeout, SQLi & XSS Resistance |
+-------------------------+-------------------------------------------------------------+
| 4. Integration Testing  | PHP <-> MySQL, HRIS <-> DSA, Leave <-> Balance deduction,  |
|                         | Leave <-> Queue, Notification Dispatch <-> WebSocket Outbox|
+-------------------------+-------------------------------------------------------------+
| 5. Error & Negative     | Duplicate entities, Inverted date ranges, Weekend zero-days |
|                         | Overdrawn quotas, Inactive workforce guards, Nonexistent IDs|
+-------------------------+-------------------------------------------------------------+
| 6. Regression Testing   | 379 Automated PHPUnit tests across 1,729+ assertions        |
+-------------------------+-------------------------------------------------------------+
| 7. Defect Tracking      | Root cause analysis, surgical repairs, regression re-tests  |
+-------------------------+-------------------------------------------------------------+
| 8. Release Assessment   | Production readiness sign-off and deployment criteria       |
+-------------------------+-------------------------------------------------------------+
```

---

## 3. Test Environments & System Topology

### 3.1 Host & Container Architecture
- **Host Operating System:** Windows 11 Pro (x86_64)
- **Host PHP Runtime:** PHP 8.5.12 (CLI) with `pdo_mysql`, `mbstring`, `openssl`, `sodium`
- **Docker Compose Containers:**
  - `hris_app`: PHP 8.2+ FPM container servicing application execution.
  - `hris_db`: MySQL 8.0.x container (port 3307 on host, 3306 internal).
  - `hris_realtime`: Ratchet WebSocket daemon listening on port 8090.
  - `hris_nginx`: Reverse proxy routing HTTP traffic on port 8080 and `/ws` to port 8090.
- **Dependency Management:** Composer 2.x
- **Testing Framework:** PHPUnit 13.4.3

### 3.2 Data Tier & State Isolation
- Test suites interact with transactional mock fixtures or dedicated database connections.
- Transactional rollbacks (`beginTransaction()` / `rollBack()`) ensure tests leave zero lingering state in database tables (`employees`, `leave_requests`, `leave_balances`, `users`, `activity_logs`, `notifications`).

---

## 4. Role-Based Access Control (RBAC) Specification

The system enforces a strict 2-Role Security Model:

| System Role | Authorized Domain & Capabilities | Blocked Operations |
| :--- | :--- | :--- |
| **HR (`hr`)** | Administrative dashboard, user management, employee directory CRUD, department & position management, leave policy & quota adjustments, leave review/approve/reject, reports & analytics, activity audit trails, DSA laboratory. | Employee self-service personal profile filing, personal dashboard actions, self-approval of own leave requests. |
| **Employee (`employee`)** | Self-service dashboard, personal profile view, own leave balance review, leave application filing, own pending leave cancellation, personal notifications. | All `/hr/*` routes, administrative settings, other employees' records (IDOR protected), leave approval/rejection, reports, activity logs, DSA laboratory. |

---

## 5. Test Categories & Methodologies

### 5.1 Functional Testing
- Verify end-to-end user workflows from authentication through profile management and leave filing.
- Ensure correct database state transitions: `Pending` -> `Approved`, `Pending` -> `Rejected`, `Pending` -> `Cancelled`.
- Verify weekend filtering rules (e.g., Saturday–Sunday evaluated as 0 working days).

### 5.2 Data Structures and Algorithms (DSA) Verification
- Execute actual native PHP algorithmic classes (`app/DSA/*`) rather than client-side simulations.
- Search: Linear Search ($O(N)$), Binary Search ($O(\log N)$) on sorted records.
- Sorting: Bubble Sort, Selection Sort, Insertion Sort, Merge Sort, Quick Sort.
- Dynamic Structures:
  - `Stack`: LIFO recent activity history.
  - `Queue`: FIFO leave approval processing pipeline.
  - `LinkedList`: Singly linked list for historical leave event chaining.
  - `HashTable`: Bucket-based direct employee lookup by number.
  - `BinarySearchTree`: In-order hierarchical employee directory traversal.
  - `RecursionHelper`: Recursive leave accrual and department hierarchy traversal.

### 5.3 Security & Penetration Testing
- **Unauthorized Bypass:** Direct GET/POST to `/hr/*`, `/reports`, `/activity-logs`, `/dsa` without active session fails closed.
- **Horizontal Privilege Escalation (IDOR):** Employee A attempting to view or cancel Employee B's leave request is rejected.
- **CSRF Token Validation:** State-changing HTTP verbs (POST, PUT, DELETE, PATCH) missing `_csrf_token` or `X-CSRF-TOKEN` fail with 403.
- **Session Security:** Session ID regeneration on privilege transition; strict 30-minute inactivity timeout; background requests (`X-HRIS-Background: 1`) do not artificially extend sessions.
- **Injection Sanitization:** Parameterized SQL queries via PDO; context-aware output escaping via `e()`.

### 5.4 Error-Handling & Negative Testing
- Submitting inverted date ranges (start > end) returns validation errors.
- Filing for 0 working days (weekends only) is blocked.
- Approving leaves when requested days exceed remaining quota is rejected with `insufficient_balance`.
- Terminated/inactive employees cannot file requests or have requests approved.
- Re-approving an already approved request or cancelling an already cancelled request fails safely.
- Mid-transaction failure injection triggers complete atomic rollback without partial balance deduction.

---

## 6. Entry and Exit Criteria

### Entry Criteria
1. Application codebase stabilized with all Phase 1–13 modules integrated.
2. Docker Compose services (`hris_app`, `hris_db`, `hris_realtime`, `hris_nginx`) online.
3. PHPUnit test suite configured and operational.

### Exit Criteria (Quality Gate)
1. **100% Test Pass Rate:** All 379 automated tests execute cleanly with 0 failures, 0 errors.
2. **0 Deprecations & Notices:** PHPUnit output contains 0 deprecations, 0 notices, 0 warnings.
3. **Zero Open Critical or Major Defects:** All reported security and operational defects verified and retested.
4. **Complete QA Documentation:** Test Plan, Test Cases, Results, Defect Log, Retest Log, and Summary delivered in `docs/qa/`.

