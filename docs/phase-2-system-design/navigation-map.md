# Application Navigation Map & Route Authorization
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 2 — System Analysis and Design  
**Date:** 2026-10-11  
**Status:** Approved for Baseline Design  

---

## 1. Global Navigation Architecture

The application implements a role-partitioned navigation structure. Unauthenticated visitors are confined to `/login`, while authenticated sessions are routed into dedicated dashboards and authorized menus.

```
+-----------------------------------------------------------------------------------+
|                           PUBLIC / UNAUTHENTICATED                                |
|                               /login  (GET, POST)                                 |
+-----------------------------------------+-----------------------------------------+
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                            AUTHENTICATED ROOT ROUTER                              |
+-----------------------------------------+-----------------------------------------+
                    |                                         |
                    v                                         v
+---------------------------------------+ +---------------------------------------+
|        ADMINISTRATOR / HR PORTAL      | |            EMPLOYEE PORTAL            |
|  - /admin/dashboard                   | |  - /dashboard                         |
|  - /employees (List, New, Edit, Show) | |  - /profile                           |
|  - /departments & /positions          | |  - /leaves/balance                    |
|  - /leaves/types & /leaves/manage     | |  - /leaves/apply                      |
|  - /reports (Employee, Leave, Dept)   | |  - /leaves/history                    |
|  - /activity-logs                     | |  - /notifications                     |
|  - /dsa/playground & /dsa/visualizer  | |  - /logout                            |
|  - /notifications & /logout           | |                                       |
+---------------------------------------+ +---------------------------------------+
```

---

## 2. Administrator / HR Portal Navigation Map

| Menu Section | Page Name / Action | HTTP Route | Access Guard | Primary Operation / Description |
| :--- | :--- | :--- | :--- | :--- |
| **Executive** | Dashboard | `GET /admin/dashboard` | `auth, role:admin,hr` | Displays aggregate metrics: Total Employees, Active Count, Pending Leaves, Recent Activity Stack widget. |
| **Workforce** | Employee Directory | `GET /employees` | `auth, role:admin,hr` | Searchable directory of employees with status filters and quick actions. |
| | Add Employee | `GET /employees/create`<br/>`POST /employees/create` | `auth, role:admin,hr` | Form to register a new employee; auto-initializes leave balances upon success. |
| | View Profile | `GET /employees/{id}` | `auth, role:admin,hr` | Comprehensive employee profile card with contact, job, and history. |
| | Edit Employee | `GET /employees/{id}/edit`<br/>`POST /employees/{id}/edit` | `auth, role:admin,hr` | Update employee information and department/position allocations. |
| | Deactivate | `POST /employees/{id}/deactivate` | `auth, role:admin,hr` | Soft-deactivates employee (`employment_status = 'Inactive'`). |
| **Organization**| Departments | `GET /departments`<br/>`POST /departments` | `auth, role:admin,hr` | List and create operational departments. |
| | Edit Department | `GET /departments/{id}/edit`<br/>`POST /departments/{id}/edit` | `auth, role:admin,hr` | Update department details. |
| | Positions | `GET /positions`<br/>`POST /positions` | `auth, role:admin,hr` | List and create job positions tied to departments. |
| | Edit Position | `GET /positions/{id}/edit`<br/>`POST /positions/{id}/edit` | `auth, role:admin,hr` | Update job position title and department. |
| **Leaves** | Leave Policy & Types | `GET /leaves/types`<br/>`POST /leaves/types` | `auth, role:admin,hr` | Configure leave categories and default allocated days. |
| | Employee Balances | `GET /leaves/balances` | `auth, role:admin,hr` | Overview of all employee leave balances across categories for current year. |
| | Pending Leave Queue | `GET /leaves/manage` | `auth, role:admin,hr` | Review pending leave requests ordered in FIFO queue. |
| | Approve Leave | `POST /leaves/{id}/approve` | `auth, role:admin,hr` | Transaction-safe approval with atomic balance deduction. |
| | Reject Leave | `POST /leaves/{id}/reject` | `auth, role:admin,hr` | Reject request without deducting balance; record reason. |
| | All Leave History | `GET /leaves/history/all` | `auth, role:admin,hr` | Global audit log of historical leave applications across all departments. |
| **Analytics** | Employee Report | `GET /reports/employee` | `auth, role:admin,hr` | Workforce headcount, position distribution, active vs inactive ratio. |
| | Leave Utilization | `GET /reports/leave` | `auth, role:admin,hr` | Aggregated leave consumption by category, department, and month. |
| | Department Summary | `GET /reports/department` | `auth, role:admin,hr` | Departmental workforce strength and leave statistics. |
| **System** | Activity Audit Log | `GET /activity-logs` | `auth, role:admin,hr` | Chronological audit trail of all state-altering system events. |
| | Notifications | `GET /notifications` | `auth, role:admin,hr` | Administrative notifications feed. |
| **DSA Lab** | Algorithm Playground | `GET /dsa/playground` | `auth, role:admin,hr` | Interactive test bench for custom PHP data structures (Stack, Queue, BST, etc.). |
| | Performance Benchmarks| `GET /dsa/visualizer` | `auth, role:admin,hr` | Multi-scale sorting and searching execution benchmark comparison matrix. |
| **Session** | Logout | `POST /logout` | `auth` | Clears session and redirects to `/login`. |

---

## 3. Employee Portal Navigation Map

| Menu Section | Page Name / Action | HTTP Route | Access Guard | Primary Operation / Description |
| :--- | :--- | :--- | :--- | :--- |
| **Main** | Employee Dashboard | `GET /dashboard` | `auth, role:employee` | Displays personal summary: allocated vs remaining leave balances, active request status, unread alerts. |
| **Profile** | My Profile | `GET /profile` | `auth, role:employee` | Read-only view of authenticated employee's record, job title, department, date hired. |
| **Leave Portal**| Leave Balances | `GET /leaves/balance` | `auth, role:employee` | Detailed personal balance cards (Allocated, Used, Remaining) per leave category. |
| | File Leave Application| `GET /leaves/apply`<br/>`POST /leaves/apply` | `auth, role:employee` | Interactive filing form with auto-duration calculation and collision pre-checks. |
| | My Leave History | `GET /leaves/history` | `auth, role:employee` | Personal chronological leave log with status indicators (Pending, Approved, Rejected, Cancelled). |
| | Cancel Pending Leave | `POST /leaves/{id}/cancel` | `auth, role:employee` | Self-service cancellation enabled exclusively for requests in `Pending` state. |
| **Alerts** | Notifications | `GET /notifications` | `auth, role:employee` | Personal feed of approval/rejection updates and status notices. |
| **Session** | Logout | `POST /logout` | `auth` | Clears session and redirects to `/login`. |

---

## 4. Route Security Gating Mechanism

* **Unauthenticated Interception:** Any request hitting a route protected by `auth` without an active `$_SESSION['user']` is intercepted by `Middleware::authenticate()` and redirected to `/login?redirect=...`.
* **Privilege Interception:** Any request hitting `/admin/*` or administrative employee CRUD endpoints where `$_SESSION['user']['role']` is `employee` is intercepted by `Middleware::authorize(['admin', 'hr'])`, returning a `403 Forbidden` response and logging the event in `activity_logs`.
