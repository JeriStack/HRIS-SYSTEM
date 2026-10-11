# Detailed System Use Cases
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 2 — System Analysis and Design  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Overview of System Actors

* **Administrator / HR (`ACT-ADMIN`):** High-privilege workforce administrator with global oversight over employees, departments, job positions, leave policies, transactional approval queues, system reports, audit logs, and the DSA evaluation lab.
* **Employee (`ACT-EMP`):** Self-service portal user authorized to review their personal profile, inspect leave balances, file leave requests, cancel pending requests, and monitor notification alerts.

---

## 2. Core Authentication & Access Use Cases

### `UC-AUTH-01`: User Login
* **Primary Actor:** `ACT-ADMIN`, `ACT-EMP`
* **Goal:** Authenticate the user and establish a secure, role-restricted session.
* **Preconditions:** User has an active account in the `users` table and is on `/login`.
* **Main Success Flow:**
  1. Actor submits username, password, and CSRF token via POST.
  2. System validates CSRF token and input formats.
  3. System looks up user by username via prepared statement.
  4. System verifies password hash with `password_verify()`.
  5. System regenerates session ID via `session_regenerate_id(true)`.
  6. System loads user role and linked `employee_id` into `$_SESSION`.
  7. System records `LOGIN` in `activity_logs`.
  8. System redirects actor to their respective dashboard (`/dashboard` or `/admin/dashboard`).
* **Alternative Flows:**
  * *4a. Account Inactive:* If the linked employee has `employment_status = 'Inactive'`, login is rejected with notice "Account is deactivated."
* **Exception Flows:**
  * *2a. Invalid CSRF:* System aborts with 403 Forbidden.
  * *3a/4b. Invalid Credentials:* System renders error "Invalid username or password" without disclosing which field failed (`NFR-SEC-006`).
* **Postconditions:** Authenticated session active; user routed to authorized dashboard.
* **Traceable FR IDs:** `FR-AUTH-001`, `FR-AUTH-002`, `FR-AUTH-003`, `FR-AUTH-006`, `FR-LOG-001`.

---

### `UC-AUTH-02`: User Logout
* **Primary Actor:** `ACT-ADMIN`, `ACT-EMP`
* **Goal:** Securely terminate the active user session.
* **Preconditions:** Actor is currently logged in.
* **Main Success Flow:**
  1. Actor clicks "Logout" button.
  2. System records `LOGOUT` in `activity_logs`.
  3. System unsets all session variables (`$_SESSION = []`).
  4. System destroys session cookie and session storage (`session_destroy()`).
  5. System redirects actor to `/login` with feedback "You have been logged out."
* **Postconditions:** Session is completely destroyed on the server.
* **Traceable FR IDs:** `FR-AUTH-005`, `FR-LOG-001`.

---

## 3. Employee Management Use Cases

### `UC-EMP-01`: Register New Employee
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Add a new employee record and establish organizational linkage.
* **Preconditions:** Actor is authenticated as Administrator/HR; departments and positions exist.
* **Main Success Flow:**
  1. Actor navigates to `/employees/create` and fills employee information.
  2. Actor submits form with CSRF token.
  3. System validates inputs: employee number uniqueness, valid email, valid dates, department/position existence (`BR-001`, `BR-002`).
  4. System persists record to `employees` table with `employment_status = 'Active'`.
  5. System automatically initializes current-year leave balance records for all active leave types (`FR-LBAL-002`).
  6. System creates `EMPLOYEE_CREATE` entry in `activity_logs`.
  7. System redirects to `/employees` with success notification.
* **Exception Flows:**
  * *3a. Duplicate Employee Number:* System displays "Employee number already exists."
  * *3b. Validation Failure:* Form redisplayed with specific field errors.
* **Postconditions:** New employee record persisted; leave balances initialized; audit log created.
* **Traceable FR IDs:** `FR-EMP-001`, `FR-EMP-002`, `FR-LBAL-002`, `FR-LOG-001`.

---

### `UC-EMP-02`: Soft-Deactivate Employee
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Deactivate an employee from active operations while preserving historical data (`BR-003`).
* **Preconditions:** Actor is authenticated as Administrator/HR; target employee exists.
* **Main Success Flow:**
  1. Actor views employee details and clicks "Deactivate Employee".
  2. System presents confirmation modal (`NFR-USA-002`).
  3. Actor confirms action.
  4. System updates employee record: `SET employment_status = 'Inactive'`.
  5. System records `EMPLOYEE_DEACTIVATE` in `activity_logs`.
  6. System renders updated employee profile with status badge "Inactive".
* **Postconditions:** Employee cannot file leave or log in; all historical leave records remain intact.
* **Traceable FR IDs:** `FR-EMP-004`, `FR-LOG-001`, `NFR-USA-002`.

---

### `UC-EMP-03`: Search and Sort Employee Directory
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Query and sort employee records using custom PHP DSA algorithms.
* **Preconditions:** Actor is on `/employees` or `/dsa/playground`.
* **Main Success Flow:**
  1. Actor inputs search key (e.g., employee number or name) or selects sort criteria (ID, Name, Date Hired, Department).
  2. System queries raw employee records from MySQL via PDO.
  3. System populates in-memory `ArrayWrapper` or `HashTable` / `BinarySearchTree`.
  4. System executes selected custom algorithm (`LinearSearch`, `BinarySearch`, or selected sorting algorithm).
  5. System renders ordered/filtered dataset along with comparison and execution timing metrics.
* **Postconditions:** Filtered/sorted results displayed without modifying underlying database records.
* **Traceable FR IDs:** `FR-EMP-006`, `FR-DSA-001`, `FR-DSA-002`, `FR-DSA-003`, `FR-DSA-004`.

---

## 4. Organization Management Use Cases

### `UC-ORG-01`: Manage Departments and Positions
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Create, modify, and manage structural units and roles.
* **Preconditions:** Actor is authenticated as Administrator/HR.
* **Main Success Flow:**
  1. Actor submits new department or position details.
  2. System verifies uniqueness and required linkages.
  3. System persists record to `departments` or `positions` table.
  4. System records action in `activity_logs`.
* **Exception Flows:**
  * *Deleting bound entity:* If actor attempts deletion of a department with linked employees or positions, MySQL foreign key triggers restriction error. System presents user error: "Cannot delete department: active employees or positions are currently assigned."
* **Postconditions:** Department/position saved; referential integrity protected (`BR-004`).
* **Traceable FR IDs:** `FR-ORG-001`, `FR-ORG-002`, `FR-ORG-003`, `FR-LOG-001`.

---

## 5. Leave Management Use Cases

### `UC-LEAVE-01`: File Leave Request
* **Primary Actor:** `ACT-EMP`
* **Goal:** Submit a formal leave application for administrative review.
* **Preconditions:** Employee is authenticated and has status `Active` (`BR-LEAVE-001`).
* **Main Success Flow:**
  1. Actor navigates to `/leaves/apply`.
  2. Form displays current remaining balances for active leave categories.
  3. Actor selects leave category, enters start date, end date, and justification.
  4. System computes duration excluding Saturdays and Sundays (`BR-LEAVE-004`).
  5. System validates:
     * `start_date <= end_date` (`BR-LEAVE-003`).
     * `duration <= remaining_days` (`BR-LEAVE-005`).
     * No overlap with existing `Pending` or `Approved` leave requests (`BR-LEAVE-006`).
  6. System inserts record into `leave_requests` with `status = 'Pending'`.
  7. System adds an in-system notification for Administrator/HR (`FR-NOTIF-001`).
  8. System logs `LEAVE_FILED` in `activity_logs`.
  9. System redirects to `/leaves/history` with green success notification.
* **Exception Flows:**
  * *5a. Insufficient balance:* System displays warning: "Requested days exceed remaining balance."
  * *5b. Overlapping dates:* System displays warning: "You already have a pending or approved leave during this period."
* **Postconditions:** Request created with `Pending` status; leave balance remains untouched.
* **Traceable FR IDs:** `FR-LEAVE-001` through `FR-LEAVE-006`, `FR-NOTIF-001`, `FR-LOG-001`.

---

### `UC-LEAVE-02`: Transaction-Safe Leave Approval
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Review and approve a pending leave request with guaranteed ACID balance deduction.
* **Preconditions:** Actor is authenticated as Administrator/HR; request exists in `Pending` status.
* **Main Success Flow:**
  1. Actor views pending request queue (`Queue` ordering).
  2. Actor clicks "Approve Request".
  3. System initiates database transaction (`$pdo->beginTransaction()`).
  4. System executes row-level check to verify:
     * Request status is still `Pending`.
     * Employee is still `Active`.
     * Leave type is still active.
     * Re-verifies `remaining_days >= requested_days` (`BR-LEAVE-012`).
  5. System updates `leave_requests`: `status = 'Approved'`, `approved_by = :uid`, `approved_at = NOW()`.
  6. System updates `leave_balances`: `used_days = used_days + :days`, `remaining_days = remaining_days - :days`.
  7. System inserts audit record into `activity_logs`.
  8. System creates in-system notification for the employee (`Your leave request has been approved`).
  9. System commits transaction (`$pdo->commit()`).
  10. System displays success notice to HR.
* **Exception Flows:**
  * *4a. Any check fails or balance insufficient:* System calls `$pdo->rollBack()`. Request status and balances remain untouched. Error displayed to HR.
* **Postconditions:** Request marked `Approved`; leave balance decremented; audit log and notification persisted atomically.
* **Traceable FR IDs:** `FR-LEAVE-008`, `FR-LBAL-004`, `FR-NOTIF-001`, `FR-LOG-001`, `NFR-INT-001`.

---

### `UC-LEAVE-03`: Reject Leave Request
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Decline a pending leave request without altering leave balances.
* **Preconditions:** Actor is authenticated as Administrator/HR; request is `Pending`.
* **Main Success Flow:**
  1. Actor clicks "Reject Request" and enters optional rationale.
  2. System updates `leave_requests` to `status = 'Rejected'`.
  3. System leaves `leave_balances` unchanged (`BR-LEAVE-010`).
  4. System records `LEAVE_REJECT` in `activity_logs`.
  5. System dispatches notification to employee.
  6. Success notice rendered to HR.
* **Postconditions:** Request marked `Rejected`; employee balance unaltered.
* **Traceable FR IDs:** `FR-LEAVE-009`, `FR-NOTIF-001`, `FR-LOG-001`.

---

### `UC-LEAVE-04`: Cancel Pending Leave Request
* **Primary Actor:** `ACT-EMP`
* **Goal:** Withdraw an unapproved leave application.
* **Preconditions:** Actor is authenticated; request belongs to actor; status is `Pending` (`BR-LEAVE-009`).
* **Main Success Flow:**
  1. Actor clicks "Cancel Request" on their pending leave in `/leaves/history`.
  2. System validates request ownership and verifies `status == 'Pending'`.
  3. System updates request: `SET status = 'Cancelled'`.
  4. System logs `LEAVE_CANCELLED` in `activity_logs`.
  5. System updates view with status badge "Cancelled". Balance is unaffected.
* **Exception Flows:**
  * *2a. Request already approved/rejected:* System aborts with notice: "Cannot cancel a processed leave request."
* **Postconditions:** Request marked `Cancelled`; no balance deduction.
* **Traceable FR IDs:** `FR-LEAVE-007`, `FR-LOG-001`.

---

## 6. DSA Laboratory & Performance Benchmarking Use Cases

### `UC-DSA-01`: Execute Algorithm Benchmarking Suite
* **Primary Actor:** `ACT-ADMIN`
* **Goal:** Empirically evaluate and compare searching or sorting algorithms across controlled datasets.
* **Preconditions:** Actor navigates to `/dsa/playground` or `/dsa/visualizer`.
* **Main Success Flow:**
  1. Actor selects algorithm category (Search: Linear vs Binary vs Hash Table; Sort: Bubble vs Selection vs Insertion vs Merge vs Quick).
  2. Actor selects dataset size $N \in \{10, 100, 500, 1000, 5000, 10000\}$.
  3. System loads synthetic in-memory employee array.
  4. System benchmarks selected algorithms using `hrtime(true)`.
  5. Algorithm tracks operational metrics: execution time ($\mu s$), comparison counts, swap counts, memory delta.
  6. System renders comparative tabular summary and visualization graphs.
* **Postconditions:** Performance metrics calculated and presented for academic evaluation.
* **Traceable FR IDs:** `FR-DSA-001` through `FR-DSA-0011`, `FR-PERF-001` through `FR-PERF-004`.
