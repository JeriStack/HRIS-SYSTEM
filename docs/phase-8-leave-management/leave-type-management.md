# Leave Classification & Policy Management

## 1. Overview

The Leave Classification module provides authorized Human Resources and Administrator users with governance over allowable absence categories, statutory entitlements, and corporate leave policies.

---

## 2. Database Schema (`leave_types`)

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `INT UNSIGNED` | `AUTO_INCREMENT`, `PRIMARY KEY` | Unique leave type identifier |
| `leave_name` | `VARCHAR(100)` | `NOT NULL`, `UNIQUE (uq_leave_types_name)` | Unique name of the leave category |
| `description` | `TEXT` | `NULL` | Policy details, guidelines, and notice rules |
| `default_days` | `INT UNSIGNED` | `NOT NULL DEFAULT 15` | Default annual allocation in working days |
| `status` | `ENUM('Active','Inactive')`| `NOT NULL DEFAULT 'Active'` | Operational availability flag |
| `created_at` | `TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | Record creation timestamp |

---

## 3. Business Rules & Operations

### 3.1 Creation
- Authorized HR/Admin users navigate to `GET /hr/leave-types/create`.
- Submitted data is validated on the server via `POST /hr/leave-types`:
  - `leave_name`: Required, 2–100 characters, unique across records (case-insensitive).
  - `default_days`: Required integer between 1 and 365.
  - `status`: Must be `Active` or `Inactive`.
- Creates record, logs action in `activity_logs`, and flashes confirmation.

### 3.2 Modification
- Accessible via `GET /hr/leave-types/{id}/edit` and processed via `POST /hr/leave-types/{id}/edit`.
- Uniqueness validation for `leave_name` excludes the record being edited (`id != :exclude_id`).
- Modifying a leave type preserves all existing historical filings and assigned balances.

### 3.3 Soft Deactivation & Historical Record Integrity
- Hard deletion (`DELETE FROM leave_types`) is strictly prohibited when records reference historical requests or balances.
- Status toggle is executed via `POST /hr/leave-types/{id}/toggle-status`:
  - Active &rarr; Inactive: Deactivates leave type.
  - Inactive leave types are excluded from the employee leave application form (`getLeaveTypes()` filters `status = 'Active'`).
  - Existing requests and historical balances retain foreign-key integrity (`ON UPDATE CASCADE ON DELETE RESTRICT`).
- Inactive &rarr; Active: Reactivates leave type for new filings.

---

## 4. Role-Based Access Control

- **HR & Administrator:** Full access to view, create, edit, and toggle status.
- **Employee:** Strictly denied (HTTP 403 Forbidden). Employees only receive the filtered active list in their filing dropdown.
