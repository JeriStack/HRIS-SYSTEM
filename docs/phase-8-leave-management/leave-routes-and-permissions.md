# Leave Routes and RBAC Permission Matrix

## 1. Complete Route Inventory

| HTTP Verb | Route URI | Controller & Action | Middleware Applied | Authorized Roles |
|---|---|---|---|---|
| `GET` | `/leaves` | `LeaveController::index` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `GET` | `/hr/leave-requests` | `LeaveController::index` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `GET` | `/hr/leave-requests/pending` | Closure &rarr; Redirect `/leaves?status=Pending` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `GET` | `/leaves/balances` | `LeaveController::balances` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `POST`| `/hr/leaves/balances/adjust` | `LeaveController::adjustBalance` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `POST`| `/hr/leaves/balances/initialize` | `LeaveController::initializeBalances` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `GET` | `/hr/leave-types` | `LeaveController::types` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `GET` | `/hr/leave-types/create` | `LeaveController::createType` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `POST`| `/hr/leave-types` | `LeaveController::storeType` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `GET` | `/hr/leave-types/{id}/edit` | `LeaveController::editType` | `AuthMiddleware`, `RoleMiddleware(['hr'])` | HR, Admin |
| `POST`| `/hr/leave-types/{id}/edit` | `LeaveController::updateType` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `POST`| `/hr/leave-types/{id}/toggle-status` | `LeaveController::toggleTypeStatus` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `POST`| `/leaves/{id}/approve` | `LeaveController::approve` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `POST`| `/leaves/{id}/reject` | `LeaveController::reject` | `AuthMiddleware`, `RoleMiddleware(['hr'])`, `CsrfMiddleware` | HR, Admin |
| `GET` | `/leaves/{id}` | `LeaveController::show` | `AuthMiddleware` | HR, Admin, or Owner Employee |
| `GET` | `/leaves/apply` | `LeaveController::apply` | `AuthMiddleware` | Authenticated Employees |
| `POST`| `/leaves/apply` | `LeaveController::store` | `AuthMiddleware`, `CsrfMiddleware` | Authenticated Employees |
| `POST`| `/leaves/{id}/cancel` | `LeaveController::cancel` | `AuthMiddleware`, `CsrfMiddleware` | Owner Employee or HR |
| `GET` | `/employee/leave-requests` | `DashboardController::employeeLeaveRequests` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Employee Only |
| `GET` | `/employee/leave-history` | `DashboardController::employeeLeaveRequests` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Employee Only |
| `GET` | `/employee/leave-balances` | `DashboardController::employeeLeaveBalances` | `AuthMiddleware`, `RoleMiddleware(['employee'])` | Employee Only |

---

## 2. Server-Side Enforcement Rules

1. **Role Middleware:**
   - Any attempt by an `employee` role user to access `/hr/leave-types*`, `/leaves`, `/leaves/balances`, or `/leaves/{id}/approve` immediately halts execution with an HTTP 403 Forbidden status.
2. **CSRF Middleware:**
   - All state-changing `POST` requests require a valid `_csrf_token` input matching the active session token. Missing or manipulated tokens result in an immediate redirect or rejection.
3. **Record Ownership Enforcement:**
   - On `/leaves/{id}`, if the user has role `employee`, the controller enforces `(int)$req['employee_id'] === (int)$user['employee_id']`. Non-matching IDs result in an HTTP 403 Forbidden ownership violation.
