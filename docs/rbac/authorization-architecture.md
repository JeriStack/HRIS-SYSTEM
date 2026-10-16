# Authorization Architecture & Enforcement Mechanism
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 2.0  
**Status:** Approved Implementation Architecture  

---

## 1. Architectural Overview

Authorization in the Employee HRIS & Leave Management System is enforced server-side before any controller action or business logic is dispatched.

The framework employs a three-tier defense model:

```
[ Incoming HTTP Request ]
           │
           ▼
[ Router: Resolve Route & Middleware Stack ]
           │
           ├─► Tier 1: AuthMiddleware (Must have active session)
           │     └─ Fail: Redirect to /login (or 401 JSON)
           │
           ├─► Tier 2: RoleMiddleware / PermissionMiddleware (RBAC verification)
           │     └─ Fail: Return HTTP 403 Forbidden (fail-closed)
           │
           ├─► Tier 3: CsrfMiddleware (State mutation validation on POST/PUT/DELETE)
           │     └─ Fail: Return HTTP 403 JSON / Redirect with flash error
           │
           ▼
[ Controller Action Execution ]
           │
           └─► Controller Resource Ownership Checks (ID parameter tampering checks)
```

---

## 2. Core Middleware Implementations

### A. AuthMiddleware (`core/AuthMiddleware.php`)
- Verifies that `isAuthenticated()` returns `true` (valid identity in `$_SESSION['user']`).
- Unauthenticated requests are halted immediately:
  - JSON requests: HTTP 401 Unauthorized (`{"status":"error","code":401,"message":"..."}`).
  - Browser requests: HTTP 302 Redirect to `/login` with flash error notice.

### B. RoleMiddleware (`core/RoleMiddleware.php`)
- Accepts a single role string (`'admin'`) or array of allowed roles (`['admin', 'hr']`).
- Normalizes role casing via `strtolower()`.
- Validates `currentUserRole()` against permitted roles.
- If the role does not match, execution halts with **HTTP 403 Forbidden**:
  - JSON requests: HTTP 403 JSON payload.
  - Browser requests: Renders `app/views/errors/403.php`.

### C. PermissionMiddleware (`core/PermissionMiddleware.php`)
- Accepts a capability name (e.g. `'manage_users'`, `'hr_dashboard'`).
- Calls `Core\Permission::can(currentUserRole(), $capability)`.
- Enforces deny-by-default logic for unknown capabilities or unrecognized roles.

### D. CsrfMiddleware (`core/CsrfMiddleware.php`)
- Validates submitted `_csrf_token` or `X-CSRF-TOKEN` header against `$_SESSION['_csrf_token']` for `POST`, `PUT`, `DELETE`, and `PATCH` requests.
- Uses constant-time string comparison (`hash_equals()`) to prevent timing attacks.

---

## 3. Fail-Closed Design Principle

The system adheres strictly to the fail-closed security principle:

1. **Unknown Roles:** If a user account possesses an invalid or corrupted role string (e.g., `'guest'`, `'superadmin'`), `Permission::can()` returns `false`, and `RoleMiddleware` returns `403 Forbidden`.
2. **Missing Employee Association:** If an employee account lacks an associated `employee_id`, queries return empty sets or explicit unavailable messages rather than leaking other employee records.
3. **Parameter Tampering:** Attempting to manipulate URL parameters (e.g. `?emp_id=5`) halts with `403 Forbidden`.
4. **Unsupported Methods:** State-changing operations via GET requests are prohibited by strict router registration.
