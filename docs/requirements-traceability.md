# Requirements Traceability Matrix (RTM)
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 2.0  
**Phase:** Cross-Phase Tracking (Phases 1, 2, 3 & 4)  
**Date:** 2026-10-11  
**Status:** Approved Baseline  

---

## 1. Overview and Purpose

The **Requirements Traceability Matrix (RTM)** establishes bidirectional traceability between system requirements, architecture layers, application modules, workflow diagrams, use cases, database schema entities, UI wireframes, and verification test cases.

---

## 2. Functional Requirements Traceability (Phases 1 to 4)

| Req ID | Title | Module | Responsible Class & Layer | DB Entity & Constraint | Screen Spec | Use Case | Test ID |
| :--- | :--- | :--- | :--- | :--- | :---: | :---: | :---: |
| `FR-AUTH-001` | User Login | `MOD-AUTH` | App: `AuthController`, `Router` | `users(username)` | `SCR-01` | `UC-AUTH-01` | `TC-001`, `TC-002` |
| `FR-AUTH-002` | Password Hash | `MOD-AUTH` | App/Data: `User.php` | `users.password (VARCHAR 255)` | `SCR-01` | `UC-AUTH-01` | `TC-SEC-01` |
| `FR-AUTH-003` | Session Regen | `MOD-AUTH` | App: `AuthController` | Session Engine | `SCR-01` | `UC-AUTH-01` | `TC-SEC-02` |
| `FR-AUTH-004` | Session Timeout | `MOD-AUTH` | App: `core/Middleware.php` | `config.php (1800s)` | All | `UC-AUTH-01` | `TC-SEC-03` |
| `FR-AUTH-005` | User Logout | `MOD-AUTH` | App: `AuthController` | Session Engine | All | `UC-AUTH-02` | `TC-001` |
| `FR-AUTH-006` | Role-Based Access| `MOD-AUTH` | App: `core/Middleware.php` | `users.role ENUM` | All | `UC-AUTH-01` | `TC-SEC-04` |
| `FR-AUTH-007` | Access Denial | `MOD-AUTH` | App: `core/Middleware.php` | `activity_logs (DENIAL)` | All | `UC-AUTH-01` | `TC-SEC-05` |
| `FR-EMP-001` | Add Employee | `MOD-EMP` | App/Data: `EmployeeController` | `employees` table | `SCR-05` | `UC-EMP-01` | `TC-003` |
| `FR-EMP-002` | Unique Emp No | `MOD-EMP` | Data: `Employee.php` | `uq_employees_emp_no` | `SCR-05` | `UC-EMP-01` | `TC-003`, `TC-INT-01` |
| `FR-EMP-003` | Edit Employee | `MOD-EMP` | App/Data: `EmployeeController` | `employees` table | `SCR-05` | `UC-EMP-01` | `TC-004` |
| `FR-EMP-004` | Soft Deactivation| `MOD-EMP`| App/Data: `EmployeeController` | `employment_status='Inactive'`| `SCR-06` | `UC-EMP-02` | `TC-005` |
| `FR-EMP-005` | Profile View | `MOD-EMP` | App/Pres: `EmployeeController` | `employees`, `positions` | `SCR-03` | `UC-EMP-01` | `TC-FUNC-01` |
| `FR-EMP-006` | Directory View | `MOD-EMP` | App/Pres: `EmployeeController` | `idx_employees_name` | `SCR-04` | `UC-EMP-03` | `TC-FUNC-02` |
| `FR-ORG-001` | Department CRUD | `MOD-DEPT`| App/Data: `DepartmentController`| `departments`, `uq_departments_name`| `SCR-07` | `UC-ORG-01` | `TC-FUNC-03` |
| `FR-ORG-002` | Position CRUD | `MOD-POS` | App/Data: `PositionController` | `positions`, `fk_positions_dept` | `SCR-08` | `UC-ORG-01` | `TC-FUNC-04` |
| `FR-ORG-003` | Referential Check| `MOD-ORG`| Data: MySQL Foreign Keys | `ON DELETE RESTRICT` | `SCR-07`, `08` | `UC-ORG-01` | `TC-INT-02` |
| `FR-LBAL-001` | Leave Types | `MOD-LTYPE`| App/Data: `LeaveController` | `leave_types`, `uq_leave_types_name` | `SCR-02` | `UC-LEAVE-01`| `TC-FUNC-05` |
| `FR-LBAL-002` | Balance Seed | `MOD-LBAL` | App/Data: `Leave.php` | `leave_balances (uq_emp_type_year)` | `SCR-05` | `UC-EMP-01` | `TC-FUNC-06` |
| `FR-LBAL-003` | Balance View | `MOD-LBAL` | App/Pres: `LeaveController` | `idx_leave_balances_emp_year` | `SCR-03` | `UC-LEAVE-01`| `TC-FUNC-07` |
| `FR-LBAL-004` | Balance Formula| `MOD-LBAL` | App/Data: `Leave.php` | `chk_leave_balances_remaining`| `SCR-03` | `UC-LEAVE-02`| `TC-FUNC-08` |
| `FR-LEAVE-001`| File Leave Form | `MOD-LREQ` | App/Pres: `LeaveController` | `leave_requests` table | `SCR-09` | `UC-LEAVE-01`| `TC-006` |
| `FR-LEAVE-002`| Date Validation| `MOD-LREQ` | App: `LeaveValidationService` | `chk_leave_requests_dates` | `SCR-09` | `UC-LEAVE-01`| `TC-006` |
| `FR-LEAVE-003`| Workday Calc | `MOD-LREQ` | App: `LeaveValidationService` | In-Memory Monday-Friday | `SCR-09` | `UC-LEAVE-01`| `TC-006` |
| `FR-LEAVE-004`| Balance Check | `MOD-LREQ` | App: `LeaveValidationService` | `leave_balances.remaining_days` | `SCR-09` | `UC-LEAVE-01`| `TC-007` |
| `FR-LEAVE-005`| Overlap Check | `MOD-LREQ` | App/Data: `Leave.php` | `idx_leave_requests_dates` | `SCR-09` | `UC-LEAVE-01`| `TC-008` |
| `FR-LEAVE-006`| Pending Status | `MOD-LREQ` | App/Data: `Leave.php` | `status = 'Pending'` | `SCR-09` | `UC-LEAVE-01`| `TC-006` |
| `FR-LEAVE-007`| Cancellation | `MOD-LPROC`| App/Data: `LeaveController` | `status = 'Cancelled'` | `SCR-11` | `UC-LEAVE-04`| `TC-010` |
| `FR-LEAVE-008`| ACID Approval | `MOD-LPROC`| App/Data: `LeaveController` | `BEGIN TX / FOR UPDATE / COMMIT` | `SCR-10` | `UC-LEAVE-02`| `TC-009` |
| `FR-LEAVE-009`| Rejection | `MOD-LPROC`| App/Data: `LeaveController` | `status = 'Rejected'` | `SCR-10` | `UC-LEAVE-03`| `TC-FUNC-09` |
| `FR-LEAVE-010`| Leave History | `MOD-LHIST`| App/Pres: `LeaveController` | `idx_leave_requests_emp_status` | `SCR-11` | `UC-LEAVE-01`| `TC-FUNC-10` |
| `FR-REP-001` | Workforce Report| `MOD-REP` | App/Pres: `ReportController` | `employees`, `departments` | `SCR-12` | `UC-REP-01` | `TC-FUNC-11` |
| `FR-REP-002` | Leave Report | `MOD-REP` | App/Pres: `ReportController` | `leave_requests`, `leave_balances` | `SCR-12` | `UC-REP-01` | `TC-FUNC-12` |
| `FR-REP-003` | Dept Report | `MOD-REP` | App/Pres: `ReportController` | `departments`, `employees` | `SCR-12` | `UC-REP-01` | `TC-FUNC-13` |
| `FR-NOTIF-001`| Internal Alerts| `MOD-NOTIF`| App/Data: `Notification.php` | `notifications` table | `SCR-13` | `UC-NOTIF-01`| `TC-FUNC-14` |
| `FR-LOG-001` | Audit Logging | `MOD-LOG` | App/Data: `ActivityLog.php` | `activity_logs` table | `SCR-13` | `UC-LOG-01` | `TC-FUNC-15` |
| `FR-DSA-001` | Array Wrapper | `MOD-DSALAB`| DSA: `ArrayWrapper.php` | In-Memory Object | `SCR-14` | `UC-DSA-01` | `TC-DSA-001` |
| `FR-DSA-002` | Linear Search | `MOD-SRT` | DSA: `LinearSearch.php` | In-Memory Sequential Traversal | `SCR-04` | `UC-EMP-03` | `TC-DSA-001` |
| `FR-DSA-003` | Binary Search | `MOD-SRT` | DSA: `BinarySearch.php` | In-Memory Sorted Array Lookup | `SCR-04` | `UC-EMP-03` | `TC-DSA-002` |
| `FR-DSA-004` | Sorting Algos | `MOD-SRT` | DSA: `Bubble`, `Merge`, `Quick`| In-Memory Direct Swapping | `SCR-04` | `UC-EMP-03` | `TC-DSA-003` |
| `FR-DSA-005` | Stack (LIFO) | `MOD-LOG` | DSA: `Stack.php` | Top-N Recent Activity Widget | `SCR-02` | `UC-LOG-01` | `TC-DSA-005` |
| `FR-DSA-006` | Queue (FIFO) | `MOD-LPROC`| DSA: `Queue.php` | Pending Leave Approval Pipeline | `SCR-10` | `UC-LEAVE-02`| `TC-DSA-004` |
| `FR-DSA-007` | Linked List | `MOD-LHIST`| DSA: `LinkedList.php` | Leave History Node Chain | `SCR-11` | `UC-LEAVE-01`| `TC-DSA-006` |
| `FR-DSA-008` | Hash Table | `MOD-EMP` | DSA: `HashTable.php` | Chained Buckets Employee Map | `SCR-14` | `UC-EMP-03` | `TC-DSA-007` |
| `FR-DSA-009` | Binary Tree BST| `MOD-EMP` | DSA: `BinarySearchTree.php` | Ordered Tree Traversal | `SCR-14` | `UC-EMP-03` | `TC-DSA-008` |
| `FR-PERF-001`| Benchmarking | `MOD-PERF` | App/DSA: `DsaController` | `hrtime(true)` Metrics Matrix | `SCR-14` | `UC-DSA-01` | `TC-PERF-01` |

---

## 3. Non-Functional Requirements Traceability

| NFR ID | Category | Enforcement Point | Database / UI Implementation | Verification Method |
| :--- | :--- | :--- | :--- | :--- |
| `NFR-SEC-001` | Passwords | `User.php` | Hashed in `users.password (VARCHAR 255)` | Unit verification of BCRYPT hash prefix `$2y$` |
| `NFR-SEC-002` | Prepared Stmts | `core/Model.php` | Parameter binding via PDO (`:param`) | Static code analysis and query audit |
| `NFR-SEC-003` | CSRF Security | `Middleware::validateCsrf` | Hidden input in `SCR-01`, `SCR-05`, `SCR-09` | Test state-changing POST without token |
| `NFR-SEC-004` | XSS Escaping | `core/functions.php` | View output escaping (`htmlspecialchars`) | Script tag injection test in form fields |
| `NFR-SEC-005` | RBAC Access | `Middleware::authorize`| Protected routing in `Router.php` | Test role access violation |
| `NFR-INT-001` | ACID Trans | `LeaveController::approve`| `BEGIN TRANSACTION`, `SELECT FOR UPDATE` | Concurrent simulated approval collision |
| `NFR-INT-002` | Foreign Keys | Engine constraints | `ON DELETE RESTRICT` across relational keys | Attempt deletion of populated department |
| `NFR-PERF-001`| In-Memory Timing| `DsaController` | Preloaded arrays before `hrtime(true)` start | Empirical metric logging in `SCR-14` |
| `NFR-USA-001` | Feedback Colors | `design-system.md` | CSS classes `.alert-success`, `.alert-error` | Visual inspection of banner components |
| `NFR-USA-002` | Modals | `design-system.md` | Consequential action modals in `SCR-06`, `10` | Manual inspection of modal triggers |
| `NFR-MNT-001` | Clean MVC | Project directory tree | Decoupled `core/`, `app/`, `docs/`, `database/` | Architecture structural audit |
