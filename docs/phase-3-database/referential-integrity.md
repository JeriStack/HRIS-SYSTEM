# Referential Integrity & Cascade Behavior Policy
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 3 — Database Design and Setup  
**Database:** `hris_db`  
**Status:** Approved Baseline  

---

## 1. Core Referential Integrity Philosophy

In enterprise Human Resource systems, **accidental record deletion can cause irreversible legal and operational damage**. A historical leave balance, an audit log, or an approved leave transaction must never be erased simply because an employee resigns or a department is reorganized.

Therefore, the system enforces a strict **Defensive Referential Integrity Policy**:
1. **Restrict Over Cascade:** Prohibit cascading deletions (`ON DELETE CASCADE`) across primary workforce and business tables.
2. **Soft Deactivation:** Employee lifecycle changes are modeled via `employment_status = 'Inactive'` rather than physical SQL `DELETE`.
3. **Audit Trail Immutability:** Activity log records must never be lost.

---

## 2. Foreign Key Behavior Specification Matrix

The table below documents the exact `ON DELETE` and `ON UPDATE` triggers enforced across all 10 foreign keys:

| Child Table | Foreign Key Column | Parent Table | `ON DELETE` Action | `ON UPDATE` Action | Rationale & Historical Integrity Protection |
| :--- | :--- | :--- | :---: | :---: | :--- |
| `positions` | `department_id` | `departments` | **`RESTRICT`** | `CASCADE` | Prevents deleting a department if job positions exist. Update cascades preserve key integrity if department IDs change. |
| `employees` | `department_id` | `departments` | **`RESTRICT`** | `CASCADE` | Prevents deleting a department while active or inactive employees are assigned to it. |
| `employees` | `position_id` | `positions` | **`RESTRICT`** | `CASCADE` | Prevents deleting a job position if current or past employees hold that title. |
| `users` | `employee_id` | `employees` | **`RESTRICT`** | `CASCADE` | An employee cannot be deleted if a system login account is bound to them. |
| `leave_balances` | `employee_id` | `employees` | **`RESTRICT`** | `CASCADE` | Prohibits deleting an employee record if historical or current leave balances exist. |
| `leave_balances` | `leave_type_id` | `leave_types` | **`RESTRICT`** | `CASCADE` | Prohibits deleting a leave category if employees hold balance records in that category. |
| `leave_requests` | `employee_id` | `employees` | **`RESTRICT`** | `CASCADE` | Prohibits deleting an employee who has filed leave applications. |
| `leave_requests` | `leave_type_id` | `leave_types` | **`RESTRICT`** | `CASCADE` | Prohibits deleting a leave type referenced by filed leave applications. |
| `leave_requests` | `approved_by` | `users` | **`RESTRICT`** | `CASCADE` | Prevents deleting an administrative user account that has approved or rejected leaves, preserving approver auditability. |
| `activity_logs` | `user_id` | `users` | **`SET NULL`** | `CASCADE` | If a user account is purged, the audit log entry is preserved with `user_id = NULL` to ensure historical compliance. |
| `notifications` | `user_id` | `users` | **`CASCADE`** | `CASCADE` | In-system notifications are user-specific alerts that may be safely purged if the user account is purged. |

---

## 3. Preservation of Historical Records via Soft Deletion

* **Mechanism:** When an employee leaves the company, their status is set via:
  ```sql
  UPDATE employees SET employment_status = 'Inactive' WHERE id = :id;
  ```
* **System Behavioral Rules:**
  * Inactive employees are excluded from active filing selectors and daily workforce directories.
  * Inactive employee logins are rejected at `/login` with notice: *"Account is deactivated."*
  * All historical leave applications, balance transactions, and audit entries remain fully queryable by HR for regulatory compliance and reporting.
