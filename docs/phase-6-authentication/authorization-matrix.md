# Phase 6: System Authorization Matrix & Permission Rules
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 1.0  
**Phase:** Phase 6 — Authentication and Authorization  
**Implementation Date:** 2026-10-11  
**Status:** Approved & Enforced  

---

## 1. Approved Roles Overview

The system strictly supports three distinct role classifications defined in the database `users.role` enum:

1. **`admin` (System Administrator):** Full system management, user credential control, organizational hierarchy setup, leave administration, audit log inspection, and DSA algorithm laboratory.
2. **`hr` (Human Resources Manager / Officer):** Workforce directory management, employee profile administration, leave request adjudication, balance adjustments, notifications, and departmental reports.
3. **`employee` (Regular Workforce Member):** Self-service portal access, viewing personal profile, inspecting personal leave balances, filing leave requests, cancelling pending requests, and viewing personal notifications.

> **Architectural Decision (`DEC-002`):**  
> In accordance with the approved baseline in `docs/decision-log.md`, `admin` and `hr` share the administrative HR management permissions tier during initial system rollout. Both roles possess access to the HR management portal, while regular employees are strictly isolated to self-service portals and their own database records.

---

## 2. Server-Side Route Authorization Matrix

| Route URI | HTTP Method | Permitted Roles | Middleware Applied | Purpose & Enforcement |
| :--- | :---: | :--- | :--- | :--- |
| `/` | `GET` | Public | None | System public landing page (SCR-00) |
| `/health` | `GET` | Public | None | System diagnostics and connectivity status |
| `/login` | `GET` | Unauthenticated | `GuestMiddleware` | Login screen (SCR-01); authenticated users redirected |
| `/login` | `POST` | Public | `CsrfMiddleware` | Process login credentials |
| `/logout` | `POST` / `GET` | Authenticated | `AuthMiddleware`, `CsrfMiddleware` | Invalidate session and clear auth cookies |
| `/dashboard` | `GET` | Authenticated | `AuthMiddleware` | Role-directed redirector to `/hr/*` or `/employee/*` |
| `/hr/dashboard` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | HR & Administration Dashboard |
| `/hr/employees` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Workforce registry & directory |
| `/hr/departments` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Department hierarchy management |
| `/hr/positions` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Position classifications management |
| `/hr/leave-requests` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Leave review and approval queue |
| `/hr/activity-logs` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | System immutable audit logs |
| `/hr/dsa-lab` | `GET` | `admin`, `hr` | `AuthMiddleware`, `RoleMiddleware(['admin','hr'])` | Data Structures & Algorithms Lab |
| `/employee/dashboard` | `GET` | `employee` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Employee Self-Service Dashboard |
| `/employee/profile` | `GET` | `employee` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Personal Employee Profile (Ownership Checked) |
| `/employee/leave-balances`| `GET` | `employee` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Personal Leave Balances API |
| `/employee/leave-requests`| `GET` | `employee` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Personal Leave Filing History |

---

## 3. Strict Record Ownership Enforcement

A critical security requirement (`NFR-SEC-004`) dictates that employees must never be permitted to inspect or manipulate another employee's records by altering query parameters or form fields.

### Enforcement Rules:
1. **Implicit Binding:** Employee data is retrieved strictly using the trusted authenticated employee ID (`$_SESSION['user']['employee_id']`), ignoring arbitrary client input.
2. **Parameter Tamper Defense:** If a request contains a target parameter (e.g. `GET /employee/profile?emp_id=1`), the controller checks:
   ```php
   if ((int)$_GET['emp_id'] !== $myEmployeeId && !hasRole(['admin', 'hr'])) {
       http_response_code(403);
       // Reject request immediately with Ownership Violation
   }
   ```
3. **No IDOR (Insecure Direct Object Reference):** URL tampering produces an immediate **HTTP 403 Forbidden** error.

---

## 4. Failure Mode & Fail-Closed Guarantee

1. **Unauthenticated Visitor:**
   - Attempting to access any protected route results in an immediate **HTTP 302 redirect to `/login`** with a security flash notice.
   - For JSON/API requests (`Accept: application/json`), returns **HTTP 401 Unauthorized**.
2. **Role Mismatch (e.g., Employee accessing `/hr/dashboard`):**
   - Returns **HTTP 403 Forbidden** with an access-denied error view.
   - For JSON/API requests, returns `{"status":"error","code":403,"message":"Forbidden..."}`.
3. **Invalid or Corrupted Role in Session:**
   - Evaluates to `false` during `RoleMiddleware` check and fails closed with **HTTP 403 Forbidden**.
