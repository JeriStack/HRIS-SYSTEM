# Role-Based Access Control (RBAC) Permission Matrix
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 2.0  
**Status:** Approved Production Architecture  
**Reconciliation Reference:** Supersedes initial Phase 1/2 grouped model (`DEC-002`) via `DEC-012`.  

---

## 1. Executive Architectural Summary

The Employee HRIS & Leave Management System implements a strict, server-side, fail-closed Role-Based Access Control (RBAC) architecture separating three distinct, mutually exclusive roles:

1. **`admin` (System Administrator):** System-wide administration, user account management, role assignment, system metrics, activity audit logging, and configuration oversight.
2. **`hr` (Human Resources Officer):** Operational human resource management, employee records, organizational departments, job positions, leave request review queues, and HR reporting.
3. **`employee` (Regular Employee):** Self-service portal restricted strictly to personal identity, viewing personal leave balances, submitting leave requests, and reviewing personal application history.

Roles are sourced directly from the database (`users.role ENUM('admin', 'hr', 'employee')`), validated upon authentication, and never trusted from client submissions.

---

## 2. Definitive Role-Permission Capability Matrix

The following baseline capability matrix is enforced centrally via `Core\Permission` and route-level middlewares:

| System Capability | Capability Key | Admin (`admin`) | HR (`hr`) | Employee (`employee`) | Enforcement Mechanism |
| :--- | :--- | :---: | :---: | :---: | :--- |
| **Administrator Dashboard** | `admin_dashboard` | **ALLOW** | **DENY (403)** | **DENY (403)** | `RoleMiddleware(['admin'])` |
| **HR Operational Dashboard** | `hr_dashboard` | **DENY (403)** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['hr'])` |
| **Employee Self-Service Portal** | `employee_dashboard` | **DENY (403)** | **DENY (403)** | **ALLOW** | `RoleMiddleware(['employee'])` |
| **User Account Directory** | `manage_users` | **ALLOW** | **DENY (403)** | **DENY (403)** | `RoleMiddleware(['admin'])` |
| **Assign Application Roles** | `assign_roles` | **ALLOW** | **DENY (403)** | **DENY (403)** | Server-side validation + CSRF |
| **Account Activation/Deactivation**| `manage_users` | **ALLOW** | **DENY (403)** | **DENY (403)** | Lockout protection enforced |
| **System Settings** | `system_settings` | **ALLOW** | **DENY (403)** | **DENY (403)** | `RoleMiddleware(['admin'])` |
| **Employee Directory & Profile** | `manage_employees` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` |
| **Create & Update Employees** | `manage_employees` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` + CSRF |
| **Deactivate Employee Records** | `manage_employees` | **ALLOW** | **ALLOW** | **DENY (403)** | Soft-deactivation (POST + CSRF) |
| **Department Management** | `manage_departments` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` + CSRF |
| **Job Position Management** | `manage_positions` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` + CSRF |
| **Leave Type Policies** | `manage_leave_types` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` |
| **Review Leave Queue (Approve/Reject)** | `review_leave_requests` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` |
| **Manage Leave Balances** | `manage_leave_balances` | **ALLOW** | **ALLOW** | **DENY (403)** | Transaction-safe balance locks |
| **View Own Profile** | `view_own_profile` | **DENY (403)** | **DENY (403)** | **ALLOW** | Strict Session Employee Ownership |
| **View Own Balances** | `view_own_leave_balances` | **DENY (403)** | **DENY (403)** | **ALLOW** | Scoped to `users.employee_id` |
| **Submit Leave Request** | `file_leave_requests` | Self-Service | Self-Service | **ALLOW** | Scoped to authenticated user |
| **Cancel Own Leave Request** | `cancel_own_leave_requests` | Policy | Policy | **ALLOW** | Pending requests only |
| **Workforce & Leave Reports** | `view_reports` | **ALLOW** | **ALLOW** | **DENY (403)** | `RoleMiddleware(['admin', 'hr'])` |
| **Activity Audit Logs** | `view_activity_logs` | **ALLOW** | **ALLOW** | **DENY (403)** | `activity_logs` audit read |
| **DSA Academic Laboratory** | `dsa_laboratory` | **ALLOW** | **ALLOW** | **DENY (403)** | Protected academic module |
| **Session Sign Out** | `logout` | **ALLOW** | **ALLOW** | **ALLOW** | POST + CSRF token required |

---

## 3. Mutual Exclusion Policy for Dashboards

A core requirement is that dashboard access is strictly partitioned:

```
                  ┌──────────────────────┐
                  │ Authenticated User   │
                  │   Session Role       │
                  └──────────┬───────────┘
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
    Role: admin          Role: hr          Role: employee
         │                   │                   │
  ┌──────┴──────┐     ┌──────┴──────┐     ┌──────┴──────┐
  │ /admin/*    │     │ /hr/*       │     │ /employee/* │
  │ Dashboard   │     │ Dashboard   │     │ Dashboard   │
  │ (ALLOW)     │     │ (ALLOW)     │     │ (ALLOW)     │
  └──────┬──────┘     └──────┬──────┘     └──────┬──────┘
         │                   │                   │
         ├─ /hr/dash: 403    ├─ /admin/*: 403    ├─ /admin/*: 403
         └─ /emp/dash: 403   └─ /emp/dash: 403   └─ /hr/*: 403
```

- An Administrator attempting to navigate to `/hr/dashboard` or `/employee/dashboard` receives **HTTP 403 Forbidden**.
- An HR Officer attempting to navigate to `/admin/dashboard` or `/employee/dashboard` receives **HTTP 403 Forbidden**.
- An Employee attempting to navigate to `/admin/dashboard` or `/hr/dashboard` receives **HTTP 403 Forbidden**.
- An Unauthenticated visitor attempting to access any dashboard is redirected to `/login` (**HTTP 302**).

---

## 4. Reconciling with Initial Planning Documentation

In Phase 1 and 2 project setup (`DEC-002`), Administrator and HR roles were temporarily grouped for rapid initial scaffolding. This refactor (`DEC-012`) establishes the production-grade separation required by system specifications:

1. **Administrator Governance:** Administrator accounts hold exclusive domain over user accounts (`users`), role assignment (`role`), and system-wide audit monitoring.
2. **HR Operations:** HR Officers cannot access user credentials, alter roles, promote their accounts, or view administrative system settings.
3. **Auditing:** All administrative role changes and status alterations are permanently logged in `activity_logs` with actor ID, IP address, user agent, and timestamp.
