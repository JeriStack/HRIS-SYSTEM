# Leave Balance Management

## 1. Overview

Leave balances track annual working day quotas, consumption history, and remaining entitlements per employee per leave classification. The database maintains authoritative records enforced by integrity constraints.

---

## 2. Database Schema (`leave_balances`)

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | `INT UNSIGNED` | `AUTO_INCREMENT`, `PRIMARY KEY` | Primary balance record ID |
| `employee_id` | `INT UNSIGNED` | `NOT NULL`, `FK -> employees(id)` | Associated employee record |
| `leave_type_id` | `INT UNSIGNED` | `NOT NULL`, `FK -> leave_types(id)`| Associated leave classification |
| `year` | `INT UNSIGNED` | `NOT NULL` | Calendar accounting year (e.g. 2026) |
| `allocated_days`| `INT` | `NOT NULL DEFAULT 0`, `CHECK >= 0` | Total approved annual quota |
| `used_days` | `INT` | `NOT NULL DEFAULT 0`, `CHECK >= 0` | Consumed days from approved requests |
| `remaining_days`| `INT` | `NOT NULL DEFAULT 0`, `CHECK >= 0` | Available balance: `allocated_days - used_days` |
| `created_at` | `TIMESTAMP` | `DEFAULT CURRENT_TIMESTAMP` | Initial assignment timestamp |

**Integrity Constraints:**
- `uq_leave_balances_emp_type_year`: Unique compound key on `(employee_id, leave_type_id, year)` preventing duplicate balance rows for the same year.
- `chk_leave_balances_allocated`, `chk_leave_balances_used`, `chk_leave_balances_remaining`: Ensure non-negative day integers.

---

## 3. Balance Lifecycle & Update Mechanics

### 3.1 Balance Initialization
- When an employee files a leave request in a year with no existing balance row, the system auto-initializes the balance using the default annual days configured in `leave_types`:
  ```sql
  INSERT INTO leave_balances (employee_id, leave_type_id, year, allocated_days, used_days, remaining_days)
  VALUES (:emp_id, :lt_id, :year, :alloc, 0, :rem);
  ```
- HR officers can also explicitly trigger balance initialization via `POST /hr/leaves/balances/initialize`.

### 3.2 Pending Request Handling (No Premature Deduction)
- Submitting a leave application creates a `Pending` request.
- `used_days` is **NOT** deducted upon filing.
- The system checks both `remaining_days` and unapproved pending reservations:
  $$\text{Effective Available} = \text{remaining\_days} - \sum \text{Pending Days}$$
  This prevents overbooking when multiple requests are filed concurrently.

### 3.3 Atomic Deduction on Approval
- Upon HR approval, `used_days` is incremented and `remaining_days` is recomputed in an atomic transaction:
  ```sql
  UPDATE leave_balances
  SET used_days = used_days + :days,
      remaining_days = GREATEST(0, allocated_days - used_days)
  WHERE employee_id = :emp_id AND leave_type_id = :lt_id AND year = :year;
  ```
- Terminal check prevents double deductions if an approval request is repeated.

### 3.4 Rejection and Cancellation Integrity
- **Rejection:** Does not touch `leave_balances`. Entitlement remains 100% intact.
- **Cancellation:** If an employee cancels a `Pending` request, no balance was deducted, so no deduction or refund is made.

### 3.5 Authorized Administrative Adjustments
- Authorized HR officers can manually adjust quotas via `POST /hr/leaves/balances/adjust`:
  - Requires: `employee_id`, `leave_type_id`, `year`, `allocated_days` (non-negative integer), and an administrative `reason`.
  - Recalculates `remaining_days = GREATEST(0, allocated_days - used_days)`.
  - Records the adjustment in `activity_logs`.
