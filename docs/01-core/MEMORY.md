# NEXORA HR — AI Agent Context Primer (MEMORY.md)

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Target:** AI Coding Agents, Context Engineering, Quick-Onboarding  
**Updated:** November 2026  

---

## 1. Core Identity & Architectural Rules
* **Brand:** NEXORA HR — *People. Processes. Progress.*
* **Role Boundary:** Strictly 2 roles: **`hr`** and **`employee`**. Under NO circumstances introduce an `admin` role. All legacy `/admin/*` routes redirect to `/hr/*`.
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
| **Dashboards & Profiles** | `app/controllers/DashboardController.php`, `app/views/dashboard/` |
| **Real-time WebSockets** | `app/realtime/WebSocketServer.php`, `app/realtime/OutboxService.php`, `public/assets/js/realtime.js` |
| **Database & Schema** | `database/schema.sql` (13 InnoDB tables), `database/migrations/`, `core/Database.php` |
| **Layouts & Partials** | `app/views/layouts/hr.php`, `app/views/partials/navbar.php`, `app/views/partials/sidebar.php` |
| **Design Tokens & Styles** | `public/assets/css/style.css`, `public/assets/css/components.css` |
| **Authoritative Documentation** | `docs/01-core/` (PRD, ARCHITECTURE, RULES, DESIGN, TASK, MEMORY), `docs/02-technical/`, `docs/README.md` |

---

## 4. Essential CLI Commands

```bash
# Run full automated test suite (592 passing tests)
./vendor/bin/phpunit

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

