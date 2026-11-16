# Authorization Architecture & Enforcement Mechanism
## Employee HRIS & Leave Management System Using PHP, MySQL, and Data Structures & Algorithms

**Document Version:** 3.0 (Dynamic Database-Driven RBAC)  
**Status:** Approved Implementation Architecture  
**Reconciliation Reference:** Upgrades static RBAC (`DEC-012`) to dynamic database-driven governance (`DEC-013`) via `Core\AuthorizationService` and `Core\PermissionRegistry`.  

---

## 1. Architectural Overview

Authorization in the Employee HRIS & Leave Management System is enforced server-side before any controller action or business logic is dispatched.

The framework employs a four-tier defense model:

```
[ Incoming HTTP Request ]
           │
           ▼
[ Router: Resolve Route & Middleware Stack ]
           │
           ├─► Tier 1: AuthMiddleware (Must have active session & active account)
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
           ├─► Dynamic Authorization Checks: can($permission), has_role($role)
           │     └─ Enforced via Core\AuthorizationService
           │
           └─► Controller Resource Ownership Checks (ID parameter tampering checks)
```

---

## 2. Dynamic Authorization Service (`Core\AuthorizationService`)

The central component of the RBAC system is `Core\AuthorizationService`. It provides:
1. **Dynamic Database Resolution:** Sourced from `roles`, `permissions`, and `role_permissions` join queries.
2. **Fallback to Authoritative Registry:** If database tables are not yet initialized or during test runs, safely falls back to default policies in `Core\PermissionRegistry`.
3. **Least-Privilege System-Protected Administrator Access:** Users with the `admin` role hold only the explicit administrative governance, HR support management (`hr_support.admin_*`, `hr_support.internal_notes`), security monitoring, and restricted read-oversight capabilities defined for `admin` in `Core\PermissionRegistry` and `role_permissions` — never a blanket wildcard across HR mutations, confidential HR leave records, or Employee self-service. Ordinary permission editing operations cannot modify the system-protected `admin` role.
4. **Deny-by-Default:** Any unassigned permission, unknown role, or unregistered capability evaluates to `false` (fail-closed).
5. **In-Memory Caching & Instant Invalidation:** Permission sets for roles are cached in memory for the duration of the request. Any administrative modification via `updateRolePermissions()` instantly clears all cached entries across all roles.
6. **Transaction Safety & Audit Logging:** Updates execute inside atomic database transactions (`START TRANSACTION` ... `COMMIT`). Every granted and revoked permission is logged to `permission_audit_logs`.

### Global Helper Functions
* `can(string $permission): bool` — Checks if the currently authenticated user possesses the specified permission or capability.
* `has_role(string $role): bool` — Checks if the currently authenticated user has the specified application role.

---

## 3. Core Middleware Implementations

### A. AuthMiddleware (`core/AuthMiddleware.php`)
- Verifies that `isAuthenticated()` returns `true` (valid identity in `$_SESSION['user']`).
- Re-verifies live account status against the database (`verifyAuthenticatedAccountState()`).
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
- Accepts a capability or permission name (e.g. `'users.view'`, `'leave.approve'`, `'manage_users'`).
- Calls `Core\Permission::can(currentUserRole(), $permission)`.
- Enforces deny-by-default logic for unknown capabilities or unrecognized roles.

### D. CsrfMiddleware (`core/CsrfMiddleware.php`)
- Validates submitted `_csrf_token` or `X-CSRF-TOKEN` header against `$_SESSION['_csrf_token']` for `POST`, `PUT`, `DELETE`, and `PATCH` requests.
- Uses constant-time string comparison (`hash_equals()`) to prevent timing attacks.

---

## 4. Fail-Closed Design Principle

The system adheres strictly to the fail-closed security principle:

1. **Unknown Roles:** If a user account possesses an invalid or corrupted role string (e.g., `'guest'`, `'superadmin'`), `Permission::can()` returns `false`, and `RoleMiddleware` returns `403 Forbidden`.
2. **Missing Employee Association:** If an employee account lacks an associated `employee_id`, queries return empty sets or explicit unavailable messages rather than leaking other employee records.
3. **Parameter Tampering:** Attempting to manipulate URL parameters (e.g. `?emp_id=5`) halts with `403 Forbidden`.
4. **Unsupported Methods:** State-changing operations via GET requests are prohibited by strict router registration.
