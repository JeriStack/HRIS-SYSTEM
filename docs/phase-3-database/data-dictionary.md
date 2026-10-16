# Comprehensive Data Dictionary
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 3 — Database Design and Setup  
**Database:** `hris_db`  
**Status:** Approved Baseline  

---

## 1. Table: `departments`
Stores functional units within the organization.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique departmental identifier. |
| `department_name` | `VARCHAR(100)` | No | None | UNIQUE (`uq_departments_name`) | None | Official organizational title (e.g., Human Resources). |
| `description` | `TEXT` | Yes | NULL | None | None | Purpose and scope of the department. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | System timestamp when department was registered. |

---

## 2. Table: `positions`
Stores distinct job titles associated with departments.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique position identifier. |
| `position_name` | `VARCHAR(100)` | No | None | UNIQUE (`uq_positions_dept_pos` [1]) | None | Job classification title (e.g., HR Officer). |
| `department_id` | `INT UNSIGNED` | No | None | FK, Index, UNIQUE [2] | `departments(id)` | Parent department hosting the job position. |
| `description` | `TEXT` | Yes | NULL | None | None | Summary of job duties and scope. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | System timestamp when position was registered. |

---

## 3. Table: `employees`
Stores comprehensive employee profile and employment status records.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Internal surrogate primary key. |
| `employee_no` | `VARCHAR(30)` | No | None | UNIQUE (`uq_employees_emp_no`), Index | None | Business workforce ID (e.g., `EMP001`). Key for binary/linear search. |
| `first_name` | `VARCHAR(50)` | No | None | Index (`idx_employees_name` [2]) | None | Employee given legal first name. |
| `middle_name` | `VARCHAR(50)` | Yes | NULL | None | None | Employee middle name or maternal surname. |
| `last_name` | `VARCHAR(50)` | No | None | Index (`idx_employees_name` [1]) | None | Employee family surname. Primary sorting key. |
| `email` | `VARCHAR(100)` | No | None | UNIQUE (`uq_employees_email`) | None | Corporate or personal email address. |
| `phone` | `VARCHAR(25)` | Yes | NULL | None | None | Mobile or telephone contact number. |
| `address` | `TEXT` | Yes | NULL | None | None | Permanent residential address. |
| `birth_date` | `DATE` | No | None | None | None | Date of birth for statutory verification. |
| `gender` | `ENUM('Male','Female','Other')` | No | None | None | None | Gender identification for statutory leaves. |
| `department_id` | `INT UNSIGNED` | No | None | FK (`fk_employees_department`), Index | `departments(id)` | Assigned organizational department. |
| `position_id` | `INT UNSIGNED` | No | None | FK (`fk_employees_position`), Index | `positions(id)` | Assigned job classification position. |
| `date_hired` | `DATE` | No | None | Index | None | Formal employment commencement date. |
| `employment_status` | `ENUM('Active','Inactive','Terminated')` | No | `'Active'` | Index (`idx_employees_status`) | None | Soft-deletion flag. Inactive employees cannot file leave or login. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | Timestamp of employee creation in database. |

---

## 4. Table: `users`
Stores application user credentials and role-based permissions.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique user account record identifier. |
| `employee_id` | `INT UNSIGNED` | Yes | NULL | FK, UNIQUE (`uq_users_employee`) | `employees(id)` | 1-to-1 linkage to employee. Nullable for standalone system admin. |
| `username` | `VARCHAR(50)` | No | None | UNIQUE (`uq_users_username`), Index | None | Unique credential username for portal authentication. |
| `password` | `VARCHAR(255)` | No | None | None | None | Cryptographic password hash (`password_hash` via BCRYPT/ARGON2ID). |
| `role` | `ENUM('admin','hr','employee')` | No | `'employee'` | Index (`idx_users_role`) | None | Access role governing route access and permission matrix. |
| `status` | `ENUM('Active','Inactive')` | No | `'Active'` | Index (`idx_users_status`) | None | Account status. Inactive users are blocked from logging in. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | Account generation timestamp. |

---

## 5. Table: `leave_types`
Configuration master for available organizational leave categories.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique leave category identifier. |
| `leave_name` | `VARCHAR(100)` | No | None | UNIQUE (`uq_leave_types_name`) | None | Category title (e.g., Vacation Leave, Sick Leave). |
| `description` | `TEXT` | Yes | NULL | None | None | Official criteria and eligibility guidelines. |
| `default_days` | `INT UNSIGNED` | No | 15 | None | None | Standard annual allocation baseline in working days. |
| `status` | `ENUM('Active','Inactive')` | No | `'Active'` | None | None | Category status. Inactive types are hidden from filing selectors. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | Leave type creation timestamp. |

---

## 6. Table: `leave_balances`
Tracks annual day allocations and usage per employee per leave category.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique leave balance record identifier. |
| `employee_id` | `INT UNSIGNED` | No | None | FK, Index, UNIQUE [1] | `employees(id)` | The employee who owns the balance quota. |
| `leave_type_id` | `INT UNSIGNED` | No | None | FK, UNIQUE [2] | `leave_types(id)` | The categorized leave entitlement type. |
| `year` | `INT UNSIGNED` | No | None | Index, UNIQUE [3] | None | Fiscal year for this balance allocation (e.g., 2026). |
| `allocated_days` | `INT` | No | 0 | `CHECK (allocated_days >= 0)` | None | Total authorized leave days for this fiscal year. |
| `used_days` | `INT` | No | 0 | `CHECK (used_days >= 0)` | None | Total days consumed via approved leave requests. |
| `remaining_days` | `INT` | No | 0 | `CHECK (remaining_days >= 0)` | None | Available leave balance (`allocated - used`). |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | Record generation timestamp. |

---

## 7. Table: `leave_requests`
Maintains filed leave applications and approval state transitions.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique leave application transaction identifier. |
| `employee_id` | `INT UNSIGNED` | No | None | FK, Index | `employees(id)` | Applying employee. |
| `leave_type_id` | `INT UNSIGNED` | No | None | FK | `leave_types(id)` | Requested category of leave. |
| `start_date` | `DATE` | No | None | `CHECK (start_date <= end_date)`, Index | None | First calendar date of requested leave. |
| `end_date` | `DATE` | No | None | Index | None | Final calendar date of requested leave. |
| `number_of_days` | `INT UNSIGNED` | No | None | `CHECK (number_of_days > 0)` | None | Calculated duration in working days (Mon-Fri excl. weekends). |
| `reason` | `TEXT` | No | None | None | None | Employee textual justification for request. |
| `status` | `ENUM('Pending','Approved','Rejected','Cancelled')` | No | `'Pending'` | Index (`idx_leave_requests_status`) | None | Lifecycle state. Only `Pending` can be cancelled or approved. |
| `approved_by` | `INT UNSIGNED` | Yes | NULL | FK (`fk_leave_requests_approver`) | `users(id)` | Administrator/HR user who approved or rejected the filing. |
| `approved_at` | `DATETIME` | Yes | NULL | None | None | Date and time when the approval or rejection occurred. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | Index | None | Timestamp when employee submitted the request. |

---

## 8. Table: `activity_logs`
Immutable audit log recording critical system events and security operations.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique audit event log sequence number. |
| `user_id` | `INT UNSIGNED` | Yes | NULL | FK (`ON DELETE SET NULL`) | `users(id)` | User who executed the action (NULL for system events). |
| `action` | `VARCHAR(50)` | No | None | Index (`idx_activity_logs_action`) | None | Standardized verb (e.g., `LOGIN`, `APPROVE_LEAVE`). |
| `description` | `TEXT` | No | None | None | None | Human-readable explanation of the state change. |
| `entity_type` | `VARCHAR(50)` | Yes | NULL | None | None | Affected database entity table (e.g., `leave_requests`). |
| `entity_id` | `VARCHAR(50)` | Yes | NULL | None | None | Primary key of affected record (e.g., `4`). |
| `ip_address` | `VARCHAR(45)` | Yes | NULL | None | None | Client IP address (supports IPv4 and IPv6). |
| `user_agent` | `TEXT` | Yes | NULL | None | None | Browser client User-Agent string for audit verification. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | Index (`idx_activity_logs_created`) | None | Precise timestamp when action occurred. |

---

## 9. Table: `notifications` (Approved Extension)
Internal notification feed for system status and workflow alerts.

| Column Name | Data Type | Nullable | Default | Keys & Constraints | References | Description & Purpose |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| `id` | `INT UNSIGNED` | No | AUTO_INCREMENT | PK | None | Unique notification record identifier. |
| `user_id` | `INT UNSIGNED` | No | None | FK, Index (`ON DELETE CASCADE`) | `users(id)` | Recipient user account. |
| `title` | `VARCHAR(150)` | No | None | None | None | Brief subject line of the notification. |
| `message` | `TEXT` | No | None | None | None | Detailed notification message body. |
| `type` | `ENUM('info','success','warning','error')` | No | `'info'` | None | None | Visual category for icon and color rendering. |
| `is_read` | `TINYINT(1)` | No | 0 | Index (`idx_notifications_user_read`) | None | Read status flag (0 = unread, 1 = read). |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | None | None | Dispatch timestamp. |
