# Role-Based Access Control (RBAC) Permission Matrix
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 3.0 (Dynamic Database-Driven RBAC)  
**Status:** Approved Production Architecture  
**Reconciliation Reference:** Upgrades static RBAC (`DEC-012`) to dynamic database-driven governance (`DEC-013`) via `Core\AuthorizationService` and `Core\PermissionRegistry`.  

---

## 1. Executive Architectural Summary

The Employee HRIS & Leave Management System implements a strict, server-side, fail-closed, database-driven Role-Based Access Control (RBAC) architecture separating three distinct, mutually exclusive roles:

1. **`admin` (System Administrator):** System-wide administration, user account management, dynamic role permission governance (`/admin/roles`), system metrics, activity audit logging, and configuration oversight. Admin retains full access to all registered application permissions; ordinary permission management cannot strip the Admin role of permissions.
2. **`hr` (Human Resources Officer):** Operational human resource management, employee records, organizational departments, job positions, leave request review queues, and HR reporting. Configurable by Admin.
3. **`employee` (Regular Employee):** Self-service portal restricted strictly to personal identity, viewing personal leave balances, submitting leave requests, and reviewing personal application history. Configurable by Admin.

Roles are sourced directly from the database (`users.role ENUM('admin', 'hr', 'employee')` and `users.role_id`), validated upon authentication, and never trusted from client submissions.

---

## 2. Dynamic 33-Permission Registry

All application permissions are registered in `Core\PermissionRegistry` across 7 logical modules. Default policies are seeded in the database (`role_permissions`) and dynamically managed by Administrators via `/admin/roles`.

| Module | Permission Identifier | Description | Admin (`admin`) | HR (`hr`) Default | Employee (`employee`) Default |
| :--- | :--- | :--- | :---: | :---: | :---: |
| **System** | `admin.access` | Access administrator portal and overview | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `users.view` | View user accounts directory | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `users.create` | Create new user accounts | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `users.edit` | Modify existing user account profiles | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `users.deactivate` | Deactivate user accounts | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `users.reactivate` | Reactivate inactive user accounts | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `roles.view` | View role and permission assignments | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `roles.manage` | Modify role permissions and policies | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `admin.provision` | Provision new administrator accounts | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `settings.general` | Manage application configuration | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `settings.notifications` | Manage notification delivery channels | **ALLOW (Immutable)** | DENY | DENY |
| **System** | `system.health` | Inspect system runtime and health | **ALLOW (Immutable)** | DENY | DENY |
| **Organization** | `employees.view` | View employee directory and records | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `employees.create` | Add new employee personnel records | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `employees.edit` | Update employee information | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `employees.deactivate` | Deactivate employee employment records | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `departments.view` | View department listings | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `departments.manage` | Create, edit, and remove departments | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `positions.view` | View job position listings | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Organization** | `positions.manage` | Create, edit, and remove job positions | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave.view_all` | View all workforce leave requests | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave.approve` | Approve submitted leave applications | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave.reject` | Reject submitted leave applications | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave.balances.view` | Inspect employee leave quotas | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave.balances.manage` | Adjust and initialize leave balances | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave.policies.manage` | Configure leave rules and entitlements | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave_policies.manage` | Create, review, and activate versioned statutory and company leave policies | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave_qualifications.verify` | Verify Solo Parent ID Cards (SPIC), paternity marital records, and SIL exclusions | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Leave** | `leave_confidential.view` | View unmasked VAWC (RA 9262) and Special Leave for Women (RA 9710) details and documents | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Reports** | `reports.view` | Access workforce analytics & reports | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Reports** | `reports.export` | Export reports to CSV or print formats | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Security** | `audit.view` | View system activity & security logs | **ALLOW (Immutable)** | **ALLOW** | DENY |
| **Security** | `security.manage` | Manage authentication and 2FA settings | **ALLOW (Immutable)** | DENY | DENY |
| **Self-Service** | `self.profile.view` | View own profile information | **ALLOW (Immutable)** | **ALLOW** | **ALLOW** |
| **Self-Service** | `self.leave.apply` | Submit own leave applications | **ALLOW (Immutable)** | **ALLOW** | **ALLOW** |
| **Self-Service** | `self.leave.cancel` | Cancel own pending leave applications | **ALLOW (Immutable)** | **ALLOW** | **ALLOW** |

---

## 3. Administrative Permission Governance (`/admin/roles`)

Administrators manage permissions dynamically through `/admin/roles`:
1. **Interactive Toggle Matrix:** Displays all 33 permissions grouped into 7 modules with real-time toggle switches for `HR` and `Employee` roles.
2. **Immutable Admin Protection:** Admin permissions are permanently checked and locked (`disabled`) to prevent accidental administrative lockout or self-privilege revoking.
3. **Transaction Safety:** Role permission updates are executed within atomic database transactions (`START TRANSACTION` ... `COMMIT`).
4. **Audit Logging:** Every modification writes granular audit entries to `permission_audit_logs`, tracking `actor_user_id`, `role_id`, `action` (`granted` / `revoked`), `permission_id`, IP address, and user agent.
5. **Instant Invalidation:** In-memory permission caches are cleared immediately upon commit, applying permission changes on the user's very next HTTP request without requiring re-login.

---

## 4. Definitive Legacy Compatibility Matrix

For backward compatibility with existing route definitions, `Core\Permission` maps legacy capability keys to new granular identifiers:

| System Capability | Legacy Capability Key | Granular Permission Token | Admin (`admin`) | HR (`hr`) | Employee (`employee`) |
| :--- | :--- | :--- | :---: | :---: | :---: |
| **Administrator Dashboard** | `admin_dashboard` | `admin.access` | **ALLOW** | **DENY (403)** | **DENY (403)** |
| **HR Operational Dashboard** | `hr_dashboard` | `employees.view` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Employee Self-Service Portal** | `employee_dashboard` | `self.profile.view` | **ALLOW** | **ALLOW** | **ALLOW** |
| **User Account Directory** | `manage_users` | `users.view` | **ALLOW** | **DENY (403)** | **DENY (403)** |
| **Assign Application Roles** | `assign_roles` | `roles.manage` | **ALLOW** | **DENY (403)** | **DENY (403)** |
| **System Settings** | `system_settings` | `settings.general` | **ALLOW** | **DENY (403)** | **DENY (403)** |
| **Employee Directory & Profile** | `manage_employees` | `employees.view` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Department Management** | `manage_departments` | `departments.manage` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Job Position Management** | `manage_positions` | `positions.manage` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Leave Type Policies** | `manage_leave_types` | `leave.policies.manage` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Review Leave Queue** | `review_leave_requests` | `leave.approve` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Manage Leave Balances** | `manage_leave_balances` | `leave.balances.manage` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **View Own Profile** | `view_own_profile` | `self.profile.view` | **ALLOW** | **ALLOW** | **ALLOW** |
| **View Own Balances** | `view_own_leave_balances` | `self.profile.view` | **ALLOW** | **ALLOW** | **ALLOW** |
| **Submit Leave Request** | `file_leave_requests` | `self.leave.apply` | **ALLOW** | **ALLOW** | **ALLOW** |
| **Cancel Own Leave Request** | `cancel_own_leave_requests` | `self.leave.cancel` | **ALLOW** | **ALLOW** | **ALLOW** |
| **Workforce & Leave Reports** | `view_reports` | `reports.view` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Activity Audit Logs** | `view_activity_logs` | `audit.view` | **ALLOW** | **ALLOW** | **DENY (403)** |
| **Session Sign Out** | `logout` | (authenticated) | **ALLOW** | **ALLOW** | **ALLOW** |

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
