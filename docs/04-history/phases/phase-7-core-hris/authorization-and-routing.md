# Phase 7: Authorization and Routing Architecture

**System:** Employee HRIS & Leave Management System  
**Module:** Core HRIS Routing & RBAC Security  
**Status:** Implemented & Verified (100% Automated Test Pass Rate)  

---

## 1. Security Architecture & Threat Model

All HRIS endpoints are protected by multi-layered server-side middleware:
1. **`AuthMiddleware`**: Enforces active authenticated session. Unauthenticated requests are immediately halted and redirected to `/login` with an informational flash message (or return HTTP 401 for API clients).
2. **`RoleMiddleware(['admin', 'hr'])`**: Evaluates `currentUserRole()`. Non-HR roles (such as regular `employee`) are blocked at the perimeter and served an HTTP 403 Forbidden page, preventing unauthorized data inspection or privilege escalation.
3. **`CsrfMiddleware`**: Validates cryptographically generated 256-bit session tokens on all state-changing HTTP requests (`POST`), mitigating Cross-Site Request Forgery vulnerabilities.

---

## 2. Complete Phase 7 Route Catalog

| HTTP Method | Route URI | Controller Handler | Middleware Stack | Description |
|---|---|---|---|---|
| **GET** | `/hr/employees` | `[EmployeeController::class, 'index']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Searchable workforce directory |
| **GET** | `/hr/employees/create` | `[EmployeeController::class, 'create']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Add employee registration form |
| **POST** | `/hr/employees` | `[EmployeeController::class, 'store']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Create employee record |
| **GET** | `/hr/employees/{id}` | `[EmployeeController::class, 'show']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | View employee profile |
| **GET** | `/hr/employees/{id}/edit` | `[EmployeeController::class, 'edit']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Edit employee record form |
| **POST** | `/hr/employees/{id}/edit` | `[EmployeeController::class, 'update']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Update employee record |
| **POST** | `/hr/employees/{id}/deactivate` | `[EmployeeController::class, 'deactivate']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Soft deactivation (Inactive) |
| **GET** | `/hr/departments` | `[DepartmentController::class, 'index']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Department directory list |
| **GET** | `/hr/departments/create` | `[DepartmentController::class, 'create']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Add department form |
| **POST** | `/hr/departments` | `[DepartmentController::class, 'store']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Create department record |
| **GET** | `/hr/departments/{id}/edit` | `[DepartmentController::class, 'edit']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Edit department form |
| **POST** | `/hr/departments/{id}/edit` | `[DepartmentController::class, 'update']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Update department details |
| **GET** | `/hr/positions` | `[PositionController::class, 'index']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Job positions directory |
| **GET** | `/hr/positions/create` | `[PositionController::class, 'create']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Add position form |
| **POST** | `/hr/positions` | `[PositionController::class, 'store']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Create position record |
| **GET** | `/hr/positions/{id}/edit` | `[PositionController::class, 'edit']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Edit position form |
| **POST** | `/hr/positions/{id}/edit` | `[PositionController::class, 'update']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])`, `CsrfMiddleware` | Update position details |
| **GET** | `/hr/api/positions-by-department/{id}` | `[PositionController::class, 'getByDepartment']` | `AuthMiddleware`, `RoleMiddleware(['admin', 'hr'])` | Dynamic JSON position feed |

---

## 3. Order-Preserving Parameterized Route Matching

To prevent parameterized segments like `{id}` from greedily capturing literal static route segments, the Core Router registers specific static endpoints **before** parameterized patterns:
1. `/hr/employees/create` is evaluated before `/hr/employees/{id}`
2. `/hr/departments/create` is evaluated before `/hr/departments/{id}/edit`
3. `/hr/positions/create` is evaluated before `/hr/positions/{id}/edit`
This guarantees deterministic routing without regex collisions.
