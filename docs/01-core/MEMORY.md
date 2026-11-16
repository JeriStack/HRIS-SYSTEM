# NEXORA HR — AI Agent Context Primer (MEMORY.md)

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.2  
**Target:** AI Coding Agents, Context Engineering, Quick-Onboarding  
**Updated:** November 2026 (ADR-012 Three-Role RBAC & Admin Governance)  

---

## 1. Core Identity & Architectural Rules
* **Brand:** NEXORA HR — *People. Processes. Progress.*
* **Role Boundary:** Exactly 3 application roles under ADR-012 & v3.2 least-privilege hardening: **`admin`**, **`hr`**, and **`employee`**.
  * **`admin`**: System-wide administrative governance, user account provisioning (`/admin/users*`), role assignment, HR-to-Admin Support Management (`/admin/support*`), private Admin internal notes, system settings, security audits, admin dashboard (`/admin/dashboard`), and restricted non-confidential workforce/leave read oversight (denied HR operational mutations, confidential leave documents, and Employee `/support`).
  * **`hr`**: Human Resources operational workforce management (employee directory, departments, positions, leave review queue, approvals/rejections, Employee Support Inbox `/hr/support*`, and submitting HR-to-Admin support tickets `/hr/help*`). Strictly segregated from application-wide role assignment, Admin provisioning, or Admin internal notes.
  * **`employee`**: Self-service workspace strictly isolated to personal profile (`/employee/profile`), personal leave filing, entitlements, Employee Help & Support (`/support*`), and notifications.
* **Controlled Admin Provisioning:** Admin accounts cannot be self-registered or created via public endpoints. The initial Administrator is bootstrapped via `bin/create_admin_user.php`.
* **Last Active Admin Protection:** System-level lockout prevention prohibits deleting, demoting, or deactivating the last remaining active Administrator. Self-demotion is strictly disallowed.
* **Stack:** Native PHP 8.5.12 MVC (no heavy external framework), MySQL 8.0 (InnoDB), Ratchet WebSocket (:8090), Docker Compose (Nginx :8080 edge proxy, Apache/PHP :80, MySQL :3306/3307).
* **Test Isolation:** Production/Dev DB: `hris_db` | Automated Test DB: `hris_test`. Never run tests against `hris_db`.

---

## 2. Key Business Rules
* **Leave Days Calculation:** Only **business days (Monday–Friday)** are counted. Saturdays and Sundays are excluded.
* **Leave Year Boundary:** Leave requests must fall strictly within the current calendar year. Cross-year requests are rejected.
* **Overlap Prevention:** Employees cannot file overlapping `Pending` or `Approved` leave requests.
* **Pessimistic Locking (FIFO Queue):** Leave approvals process in FIFO order inside an atomic transaction using `SELECT ... FOR UPDATE` on `leave_requests` and `leave_balances` to prevent race conditions and negative balances.
* **Missing Balances Policy:** Employees without an annual quota are initialized safely on-demand with defaults rather than returning null.
* **Continuous Session Verification:** `Session::verifyAuthenticatedAccountState()` runs on every request. Deactivated accounts are terminated instantly; profile attributes (`name`, `employee_no`, `department`, `position`) are synchronized live.

---

## 3. Key File Map

| Purpose | File / Directory Path |
|---|---|
| **Front Controller & Routing** | `public/index.php`, `core/Router.php` |
| **Authentication & MFA** | `app/controllers/AuthController.php`, `app/services/EmailOtpService.php`, `app/services/TotpService.php` |
| **Session & Guards** | `core/Session.php`, `core/AuthMiddleware.php`, `core/RoleMiddleware.php`, `core/CsrfMiddleware.php` |
| **Leave Engine & Locking** | `app/controllers/LeaveController.php`, `app/models/Leave.php`, `app/services/LeaveApprovalService.php` |
| **Workforce Administration** | `app/controllers/EmployeeController.php`, `app/controllers/DepartmentController.php`, `app/models/Employee.php` |
| **Announcements (DDD)** | `app/Domain/Announcement/`, `app/Application/Announcement/`, `app/Infrastructure/Persistence/Announcement/`, `app/controllers/HrAnnouncementController.php`, `app/controllers/EmployeeAnnouncementController.php` |
| **Dashboards & Profiles** | `app/controllers/DashboardController.php`, `app/views/dashboard/` |
| **Real-time WebSockets** | `app/realtime/WebSocketServer.php`, `app/realtime/OutboxService.php`, `public/assets/js/realtime.js` |
| **Database & Schema** | `database/schema.sql` (13 InnoDB tables), `database/migrations/`, `core/Database.php` |
| **Layouts & Partials** | `app/views/layouts/hr.php`, `app/views/partials/navbar.php`, `app/views/partials/sidebar.php` |
| **Design Tokens & Styles** | `public/assets/css/style.css`, `public/assets/css/components.css` |
| **Authoritative Documentation** | `docs/01-core/` (PRD, ARCHITECTURE, RULES, DESIGN, TASK, MEMORY), `docs/02-technical/`, `docs/README.md` |

---

## 4. Essential CLI Commands

```bash
# Run full automated test suite (600 passing tests)
./vendor/bin/phpunit

# Bootstrap initial Administrator account (CLI only)
php bin/create_admin_user.php --username=admin --email=admin@nexora.local --password="Password123!"

# Run PSR-12 code style baseline check (must have 0 new violations)
php bin/check-phpcs-baseline.php

# Run database migrations
php bin/migrate.php

# Start Ratchet WebSocket server daemon (:8090)
php bin/realtime-server.php

# Run Transactional Outbox poller
php bin/outbox-worker.php

# Check container health and status
docker compose ps
```

---

## 5. Agent Operational Guardrails
1. **Always Inspect First:** Read model, controller, and test files before making changes.
2. **Preserve User Changes:** Never run destructive git commands (`git clean`, `git reset --hard`) or discard working tree modifications.
3. **No Volume Pruning:** Never run `docker compose down -v` or prune `db_data` volumes.
4. **No Placeholders:** Write complete, production-ready code with explicit types and parameter validation.
5. **Verify With Real Tests:** Always run `./vendor/bin/phpunit` and `php bin/check-phpcs-baseline.php` after changes. Report actual test numbers.

