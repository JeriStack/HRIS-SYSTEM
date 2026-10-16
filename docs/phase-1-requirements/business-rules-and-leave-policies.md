# Business Rules & Leave Management Policies
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 1 — Requirements and Project Initiation  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. General HR & System Business Rules (`BR-001` to `BR-015`)

| Rule ID | Category | Business Rule Statement | Validation Mechanism |
| :--- | :--- | :--- | :--- |
| `BR-001` | Employee Integrity | **Unique Employee Identifier:** Every employee record must have a unique, non-null `employee_no` (e.g., `EMP-0001`). System must reject duplicates at both model validation and database constraint levels. | Database `UNIQUE` constraint & Model pre-validation. |
| `BR-002` | Organization Integrity | **Mandatory Organizational Linkage:** An employee must be assigned to an active department and an active job position. Positions must belong to the selected department. | Foreign key constraint & dynamic form validation. |
| `BR-003` | Lifecycle Preservation | **Soft Deactivation Policy:** Deleting an employee permanently from MySQL is prohibited. When an employee leaves, their `employment_status` is updated to `Inactive` or `Terminated`. Historical leave requests, balances, and logs are preserved. | Model update restriction; SQL `DELETE` disabled on `employees`. |
| `BR-004` | Department & Position Safety | **Restrictive Organization Deletion:** A department or position cannot be deleted if active or inactive employees are currently linked to it. | MySQL `ON DELETE RESTRICT` constraint. |
| `BR-005` | User Account Binding | **1-to-1 User-to-Employee Mapping:** An employee profile may be bound to at most one system login account (`users.employee_id`). Login accounts must have unique usernames. | Database `UNIQUE` constraints on `users.employee_id` and `users.username`. |
| `BR-006` | Data Isolation | **Strict Horizontal Isolation:** Non-administrative employees can only query, view, or modify their own personal profile data and leave requests. | Controller session extraction (`$_SESSION['employee_id']`). |
| `BR-007` | Input Sanitation | **Comprehensive Server-Side Validation:** All input (strings, dates, integers) must undergo server-side sanitization and type validation regardless of client-side HTML5 validation. | Core validation helper classes. |
| `BR-008` | Error Obfuscation | **Secure Error Translation:** Detailed SQL exceptions, tracebacks, or system internals must never be rendered to users. Technical errors must be translated to user-friendly messages (e.g., "The requested operation could not be completed. Please contact support."). | Centralized exception handler and logger. |
| `BR-009` | State Alteration Audit | **Comprehensive Auditing:** Every state-altering action (`CREATE`, `UPDATE`, `DEACTIVATE`, `APPROVE`, `REJECT`, `CANCEL`) must generate a permanent record in `activity_logs`. | Automated trigger in Model/Service layer. |
| `BR-010` | Fiscal Year Alignment | **Annual Balance Boundary:** Leave balances are assigned per calendar/fiscal year (`leave_balances.year`). Balances do not automatically roll over unless an explicit rollover rule is configured. | Balance query scoping by `year = YEAR(CURRENT_DATE)`. |

---

## 2. Detailed Leave Management Policies (`BR-LEAVE-001` to `BR-LEAVE-015`)

Leave filing, validation, queueing, and processing follow strict business rules to prevent conflicting bookings, unearned leave deductions, and inconsistent database states.

### 2.1 Leave Filing & Eligibility Rules

* `BR-LEAVE-001` **(Active Employee Eligibility):**
  Only employees whose `employment_status` is explicitly set to `Active` may submit leave applications. Inactive or terminated employees are blocked from submitting requests.
* `BR-LEAVE-002` **(Active Leave Category):**
  Leave requests can only be filed against active leave types (`leave_types.status = 'Active'`). Deactivated leave types are hidden from filing selectors.
* `BR-LEAVE-003` **(Chronological Date Consistency):**
  * The `start_date` must be less than or equal to `end_date` (`start_date <= end_date`).
  * Both dates must be valid ISO dates (`YYYY-MM-DD`).
* `BR-LEAVE-004` **(Working Days Duration Calculation):**
  * Leave duration is calculated based on **working days (Monday through Friday)**.
  * Saturdays and Sundays are excluded from the computed duration.
  * **Holiday Limitation Note:** Public holidays are currently *not* excluded automatically in Version 2 due to the absence of a designated holiday calendar table. This is documented as an unconfirmed requirement awaiting institutional policy decision.
  * Computed duration must be an integer greater than zero ($\text{duration} \ge 1$).

```
Formula:
Duration (Days) = Count of calendar dates between start_date and end_date (inclusive) 
                  WHERE DayOfWeek NOT IN ('Saturday', 'Sunday')
```

* `BR-LEAVE-005` **(Sufficient Leave Balance):**
  * Before filing, the calculated working days must not exceed the employee's `remaining_days` for that leave type in the current year.
  * If $\text{requested\_days} > \text{remaining\_days}$, the system must reject filing with message: *"Insufficient leave balance. You requested X days, but only Y days remain."*
* `BR-LEAVE-006` **(Overlapping Request Collision Check):**
  * An employee cannot submit a leave request whose date range overlaps with any of their existing requests that have status `Pending` or `Approved`.
  * Overlap Condition:
    $$\text{Existing.start\_date} \le \text{New.end\_date} \quad \text{AND} \quad \text{Existing.end\_date} \ge \text{New.start\_date}$$
  * Requests with status `Rejected` or `Cancelled` are excluded from collision checks.
* `BR-LEAVE-007` **(Mandatory Reason):**
  A textual justification (`reason`) must be supplied by the employee (minimum 5 characters, maximum 500 characters).

---

### 2.2 Leave Lifecycle & Status Transition Rules

```
                      +-------------------+
                      |   Employee Files  |
                      +---------+---------+
                                |
                                v
                      +-------------------+
                      |      PENDING      |
                      +----+--------+-----+
                           |        |
         +-----------------+        +-----------------+
         | (HR Rejects)             | (Employee       | (HR Approves)
         |                          |  Cancels)       |
         v                          v                 v
+-----------------+        +-----------------+  +-----------------+
|    REJECTED     |        |    CANCELLED    |  |    APPROVED     |
| (No Deduction)  |        | (No Deduction)  |  | (Deduct Balance)|
+-----------------+        +-----------------+  +-----------------+
```

* `BR-LEAVE-008` **(Initial Status):**
  All successfully validated leave submissions enter the system strictly with `status = 'Pending'`.
* `BR-LEAVE-009` **(Employee Cancellation Restraint):**
  * An employee can cancel their own leave request **if and only if** its status is `Pending`.
  * Once a request is `Approved` or `Rejected`, employee cancellation is disabled.
  * Upon cancellation, the request transitions to `status = 'Cancelled'`, and an audit record is created.
  * Cancellation never modifies leave balance records.
* `BR-LEAVE-010` **(Leave Rejection Restraint):**
  * Administrator/HR may reject a request with status `Pending`.
  * Status transitions to `Rejected`. An activity log and an employee notification are generated.
  * Rejection never deducts from the employee's leave balance.

---

### 2.3 Transaction-Safe Leave Approval Protocol (`BR-LEAVE-011` to `BR-LEAVE-015`)

Leave approval alters multiple interdependent records. To prevent data corruption, race conditions, or partial writes, approvals must follow a strict ACID transaction workflow.

* `BR-LEAVE-011` **(Transaction Boundary):**
  All operations in the approval sequence must be encapsulated within a single PDO database transaction:
  ```php
  $pdo->beginTransaction();
  try {
      // 1. Re-check request existence & lock row (SELECT ... FOR UPDATE)
      // 2. Verify request status is still 'Pending'
      // 3. Verify employee is still Active
      // 4. Verify leave type is still Active
      // 5. Re-check leave_balances.remaining_days >= request.number_of_days
      // 6. UPDATE leave_requests SET status = 'Approved', approved_by = :uid, approved_at = NOW()
      // 7. UPDATE leave_balances SET used_days = used_days + :days, remaining_days = remaining_days - :days
      // 8. INSERT INTO activity_logs (...)
      // 9. INSERT INTO notifications (...)
      $pdo->commit();
  } catch (Exception $e) {
      $pdo->rollBack();
      throw $e;
  }
  ```
* `BR-LEAVE-012` **(Strict Pre-Approval Revalidation):**
  The system must never assume the balance is valid merely because it was valid at the time of filing. The balance must be re-checked inside the transaction to prevent overdrafts from concurrent approvals.
* `BR-LEAVE-013` **(Single Deduction Guarantee):**
  Leave balance deduction occurs **once and only once** per approved request.
* `BR-LEAVE-014` **(Atomic Rollback):**
  If any step within the transaction fails (such as notification creation error, network timeout, or balance shortage), the entire transaction is immediately rolled back via `rollBack()`. Under no circumstances will a leave request show `Approved` with untouched balances.
* `BR-LEAVE-015` **(FIFO Queue Order):**
  For batch or administrative review, pending leave requests are presented and processed in First-In, First-Out (FIFO) chronological sequence using the custom PHP `Queue` data structure.
