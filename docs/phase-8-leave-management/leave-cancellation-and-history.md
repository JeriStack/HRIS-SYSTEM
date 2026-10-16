# Leave Cancellation & History Tracking

## 1. Employee Leave Cancellation

Employees can cancel their own pending leave applications when business conditions or personal schedules change.

### Cancellation Policy & Rules:
1. **Ownership Enforcement:**
   - The user must be authenticated.
   - The user's `employee_id` must match `leave_requests.employee_id`.
   - Any attempt to cancel another employee's request returns an immediate error without modifying data.
2. **Status Constraint:**
   - Only `Pending` requests can be cancelled.
   - Already `Approved` requests cannot be cancelled through the self-service cancellation endpoint.
3. **No Balance Reversals Required:**
   - Because `Pending` requests do not deduct balance, cancelling a pending request does not increment or refund the balance, maintaining strict balance integrity.
4. **Historical Preservation:**
   - Cancelled requests remain in `leave_requests` with `status = 'Cancelled'`. They are never permanently deleted, providing a complete audit trail.
5. **Activity Logging & Notification:**
   - Logs `LEAVE_CANCELLED` in `activity_logs`.
   - Sends notification to the HR team.

---

## 2. Leave History Views & Privacy Isolation

### 2.1 Employee Self-Service History (`/employee/leave-requests` & `/employee/leave-history`)
- Accessible strictly to the logged-in employee.
- Queries `leave_requests` filtered by `employee_id = :my_employee_id`.
- Parameter tampering attempts (e.g. `?emp_id=5`) trigger an HTTP 403 Forbidden ownership violation response (`errors/403.php`).
- Displays: Ref #, Leave Classification, Schedule, Duration, Reason, Date Filed, Status Badge, Rejection Reason (if rejected), and Cancel button (if pending).

### 2.2 HR & Administrator Organizational History (`/leaves`)
- Accessible only to authorized `hr` and `admin` roles.
- Displays all requests across all departments with status tabs and department/date filters.
- Provides deep inspection through `/leaves/{id}`.
