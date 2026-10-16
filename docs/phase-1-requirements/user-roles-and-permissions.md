# User Roles and Authorization Specification
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 1 — Requirements and Project Initiation  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. System Actor Definitions

The application defines two primary actor groups for access control:

```
                  +-----------------------------------+
                  |      AUTHENTICATED PRINCIPAL      |
                  +-----------------+-----------------+
                                    |
                    +---------------+---------------+
                    |                               |
                    v                               v
    +-------------------------------+   +-------------------------------+
    |       ADMINISTRATOR / HR      |   |           EMPLOYEE            |
    |  - Workforce Management       |   |  - Personal Profile Access    |
    |  - Leave Review & Processing  |   |  - Leave Filing & Tracking    |
    |  - Reporting & Activity Logs  |   |  - Request Self-Cancellation  |
    |  - DSA Laboratory Management  |   |  - Personal Notifications     |
    +-------------------------------+   +-------------------------------+
```

### 1.1 Administrator / HR Role Group
Per the approved project baseline plan, **Administrator** and **HR** permissions are grouped into a unified management level unless client or instructor specifications subsequently mandate strict sub-role separation.
* **Responsibilities:** Full administrative stewardship over organizational structure, employee lifecycles, leave policies, transactional approval queues, system reports, and audit trails.
* **Scope of Access:** Global organizational visibility (all departments, positions, employees, and leave records).

### 1.2 Employee Role
* **Responsibilities:** Self-service workforce participant.
* **Scope of Access:** Strictly localized visibility. An employee may only view and manipulate their own personal records, leave balances, filings, and notifications.

---

## 2. Granular Permissions Breakdown

### 2.1 Administrator / HR Permissions
1. **Authentication:**
   * Login with administrative credentials.
   * Logout and terminate active sessions.
2. **Employee Records:**
   * View full employee directory and profile summaries.
   * Register new employees (`FR-EMP-001`).
   * Update existing employee details (`FR-EMP-003`).
   * Deactivate employee accounts without hard deletion (`FR-EMP-004`).
3. **Organization Structure:**
   * Create, update, view, and delete departments (subject to foreign key restrictions).
   * Create, update, view, and delete job positions.
4. **Leave Policy & Entitlements:**
   * Create and modify leave categories and annual quotas (`FR-LBAL-001`).
   * Initialize, inspect, and adjust employee annual leave balances.
5. **Leave Processing:**
   * Access the centralized pending leave requests queue (`FR-LEAVE-008`).
   * Approve pending leave requests via transaction-safe balance deduction.
   * Reject pending leave requests with documented rationale.
   * Inspect global leave history and utilization across all employees.
6. **Reporting & Monitoring:**
   * Generate employee, leave, and departmental analytical reports.
   * Review comprehensive activity audit logs (`FR-LOG-001`).
   * Receive administrative system notifications for incoming leave filings.
7. **DSA Laboratory:**
   * Access algorithm demonstration benches and benchmark test runners.

### 2.2 Employee Permissions
1. **Authentication:**
   * Login with assigned employee credentials.
   * Logout and terminate active session.
2. **Profile & Balance:**
   * View personal employee profile (read-only for core job metadata).
   * View personalized annual leave balance breakdown (allocated, used, remaining).
3. **Leave Requests:**
   * Submit new leave applications (`FR-LEAVE-001`).
   * Cancel personal leave requests that remain in `Pending` status (`FR-LEAVE-007`).
   * View personal historical and ongoing leave requests with status updates.
4. **Notifications:**
   * Receive in-system notifications regarding request approvals, rejections, or status updates.

---

## 3. Comprehensive Role-Permission Matrix

The following matrix defines the authoritative CRUD and operational permissions across all system modules:

| Module / Resource | Action | Administrator / HR | Employee | Authorization Rule ID |
| :--- | :--- | :---: | :---: | :--- |
| **Authentication** | Login | Allowed | Allowed | `AUTH-PERM-01` |
| | Logout | Allowed | Allowed | `AUTH-PERM-02` |
| **Own Profile** | View | Allowed | Allowed | `AUTH-PERM-03` |
| | Edit (Self) | Allowed | Restricted (Self-Contact Only) | `AUTH-PERM-04` |
| **Employee Directory**| View All | Allowed | Denied | `EMP-PERM-01` |
| | Create Employee | Allowed | Denied | `EMP-PERM-02` |
| | Edit Any Employee| Allowed | Denied | `EMP-PERM-03` |
| | Deactivate Employee| Allowed | Denied | `EMP-PERM-04` |
| | Hard Delete Employee| Denied (Soft-Delete Only) | Denied | `EMP-PERM-05` |
| **Departments** | View / Create / Edit | Allowed | Denied | `ORG-PERM-01` |
| **Positions** | View / Create / Edit | Allowed | Denied | `ORG-PERM-02` |
| **Leave Types** | View / Create / Edit | Allowed | Denied | `LEAVE-PERM-01` |
| **Leave Balances** | View Own Balance | Allowed | Allowed | `LEAVE-PERM-02` |
| | View All Balances | Allowed | Denied | `LEAVE-PERM-03` |
| | Adjust Balance | Allowed | Denied | `LEAVE-PERM-04` |
| **Leave Requests** | File Leave | Allowed (Optional) | Allowed | `LEAVE-PERM-05` |
| | Cancel Own Pending | Allowed | Allowed | `LEAVE-PERM-06` |
| | View All Requests | Allowed | Denied | `LEAVE-PERM-07` |
| | View Own History | Allowed | Allowed | `LEAVE-PERM-08` |
| | Approve Request | Allowed | Denied | `LEAVE-PERM-09` |
| | Reject Request | Allowed | Denied | `LEAVE-PERM-10` |
| **Reports** | Employee Report | Allowed | Denied | `REP-PERM-01` |
| | Leave Report | Allowed | Denied | `REP-PERM-02` |
| | Department Report| Allowed | Denied | `REP-PERM-03` |
| **Audit Logs** | View Activity Logs | Allowed | Denied | `LOG-PERM-01` |
| **Notifications** | View Own Notifications | Allowed | Allowed | `NOTIF-PERM-01` |
| **DSA Laboratory** | Access Benchmarks | Allowed | Denied / Demo View | `DSA-PERM-01` |

---

## 4. Server-Side Enforcement Rules

Security is maintained under the fundamental rule: **Frontend controls (e.g., hidden buttons, navigation links omitted) never constitute access control.**

1. **Mandatory Guard Execution:**
   Every controller action must invoke `Middleware::authenticate()` and `Middleware::authorize([allowed_roles])` prior to reading parameters, rendering views, or performing business logic.
2. **Horizontal Privilege Escalation Prevention:**
   When an employee accesses `/leaves/history` or `/profile`, the controller extracts the authenticated ID directly from `$_SESSION['user']['employee_id']` rather than trusting user-tamperable URL query parameters (`?employee_id=...`).
3. **Session Hijacking Defense:**
   Sessions store IP address and User Agent signatures. If a request signature deviates significantly during an active session, the session is invalidated, and the user is redirected to `/login`.
4. **Standard Access Denial:**
   Unauthorized attempts trigger an HTTP `403 Forbidden` header and log a warning in `activity_logs` with action `UNAUTHORIZED_ACCESS_ATTEMPT`.
