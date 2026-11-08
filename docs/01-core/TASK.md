# NEXORA HR — Development Roadmap & Task Status

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Live Development Tracking  
**Date:** November 2026  
**Document Owner:** Repository Maintainer & Technical Lead  

---

## 1. Executive Status & Verified Metrics

| Metric | Measured Value | Standard / Target | Status |
|---|---|---|---|
| **PHPUnit Test Suite** | 600 tests, 2,152 assertions | 100% Passing (0 failures, 0 errors, 94 skipped) | `DONE` |
| **PSR-12 Code Style** | 235 baseline issues (0 new violations) | Baseline limit: 259 | `DONE` |
| **Database Schema** | 13 InnoDB tables, 7 core migrations | MySQL 8.0 InnoDB, zero corruption | `DONE` |
| **Role Model** | 3 Roles (`admin`, `hr`, `employee`) | Strict 3-role RBAC under ADR-012 | `DONE` |
| **Multi-Factor Auth** | Email OTP (6-digit) + TOTP Authenticator | Mandatory 2FA, 10m TTL | `DONE` |
| **Real-time Pipeline** | Ratchet WebSocket (:8090) + Outbox Worker | Transactional Outbox, sub-100ms dispatch | `DONE` |
| **Documentation** | 5 Numbered Pillars (01-core to 05-reference) | Unified single source of truth | `DONE` |
| **CI/CD Automation** | 4 Workflows (CI, E2E #44, CD #14, Unit #147) | All green checks, Node 24 actions | `DONE` |

---

## 2. Master Roadmap & Phase Breakdown

### Phase 1: Requirements & Domain Modeling (`DONE`)
- [x] Define product scope, user personas, and operational use cases.
- [x] Establish strict two-role authorization boundary (`hr` and `employee`).
- [x] Author comprehensive leave entitlement business rules (Mon-Fri calculation, cross-year restriction).

### Phase 2: Architecture & Foundation (`DONE`)
- [x] Design native 4-tier MVC framework (Router, Middleware, Controller, Model, View).
- [x] Implement fail-closed middleware pipeline (`AuthMiddleware`, `RoleMiddleware`, `CsrfMiddleware`).
- [x] Author multi-container Docker Compose infrastructure (Nginx, Apache/PHP 8.5, MySQL 8.0).

### Phase 3: Database & Migrations (`DONE`)
- [x] Author 13 InnoDB tables with explicit foreign key constraints and composite indexes.
- [x] Implement CLI database migration runner (`bin/migrate.php`).
- [x] Isolate development database (`hris_db`) from test database (`hris_test`).

### Phase 4: UI/UX & Responsive App Shell (`DONE`)
- [x] Build enterprise CSS design token system (`public/assets/css/style.css`).
- [x] Implement accessible responsive App Shell with mobile off-canvas drawer (`layouts/hr.php`).
- [x] Build WCAG 2.1 AA compliant component library (buttons, badges, cards, data tables, modals).

### Phase 5: Authentication & Multi-Factor Security (`DONE`)
- [x] Implement primary credential verification with Bcrypt (cost 12).
- [x] Implement 6-digit cryptographic Email OTP delivery via PHPMailer / SMTP.
- [x] Implement RFC 6238 TOTP authenticator backup (`TotpService`).
- [x] Implement continuous live session state re-verification (`Session::verifyAuthenticatedAccountState()`).
- [x] Implement Step-Up Authentication for sensitive HR operations (`StepUpAuthService`).

### Phase 6: Core Workforce Administration (`DONE`)
- [x] Implement employee CRUD operations with input validation and PII security.
- [x] Implement department and position governance with `ON DELETE RESTRICT` protection.
- [x] Build searchable workforce directory with real-time status toggles.

### Phase 7: Leave Management Engine (`DONE`)
- [x] Implement leave request submission with business-day calculation (excluding weekends).
- [x] Enforce overlap detection preventing concurrent active requests for the same employee.
- [x] Implement FIFO pending approval queue for HR officers.
- [x] Implement pessimistic row locking (`SELECT ... FOR UPDATE`) during leave approvals to guarantee zero double-deductions.
- [x] Implement automatic missing balance initialization policy.

### Phase 8: Real-Time Event Distribution & Outbox (`DONE`)
- [x] Build Ratchet WebSocket server daemon running on `:8090`.
- [x] Implement single-use connection ticket handshake (`/realtime/ticket`).
- [x] Implement Transactional Outbox pattern (`outbox_events` table and poller).
- [x] Implement client-side auto-reload and live badge indicator (`realtime.js`).
- [x] Implement live profile and navbar synchronization on employee update (`dbe361f`).

### Phase 9: Quality Engineering & Testing (`DONE`)
- [x] Expand PHPUnit automated test suite to 592 tests covering all models, controllers, and services.
- [x] Implement Python/Selenium E2E automation targeting Brave/Chromium.
- [x] Implement PSR-12 baseline enforcement script (`bin/check-phpcs-baseline.php`).

### Phase 10: Documentation Audit & Consolidation (`DONE`)
- [x] Complete inventory of 187 repository Markdown documents.
- [x] Propose documentation architecture and receive user approval ("Approved all").
- [x] Create 6 authoritative root-level documents (`PRD.md`, `ARCHITECTURE.md`, `RULES.md`, `DESIGN.md`, `TASK.md`, `MEMORY.md`).
- [x] Author 5 priority technical documents (`docs/02-technical/security/SECURITY.md`, `database/DATABASE.md`, `testing/TESTING.md`, `api/API.md`, `decisions/DECISIONS.md`).
- [x] Author supporting operational & reference documents (`docs/03-operations/deployment/DEPLOYMENT.md`, `troubleshooting/TROUBLESHOOTING.md`, `docs/05-reference/glossary/GLOSSARY.md`).
- [x] Update root `README.md` and `docs/README.md` as unified documentation portals.
- [x] Validate all internal Markdown links (0 broken links across 147 markdown files).
- [x] Modernize GitHub Actions workflows to Node 24 runtime (`checkout@v5`, `setup-terraform@v4`, `setup-python@v6`, `upload-artifact@v6`, `download-artifact@v7`).

### Phase 11: Admin Role & Three-Role RBAC Implementation (`DONE`)
- [x] Author schema migration `2026_11_06_add_admin_role_to_users.sql` adding `admin` to `users.role` ENUM with zero table rewrite and default preservation.
- [x] Synchronize initialization schema parity across `database/schema.sql` and `docker/mysql/init/01-schema.sql`.
- [x] Implement controlled initial Admin provisioning CLI tool (`bin/create_admin_user.php`) with password complexity, Bcrypt hashing, audit logging, and duplicate prevention.
- [x] Implement system-wide capability matrix in `core/Permission.php` separating Admin system governance, HR operational workforce, and Employee self-service.
- [x] Implement Admin route protection and backward-compatible redirects (`/admin/dashboard`, `/admin/users*`, redirect `/hr/users*` -> `/admin/users*`).
- [x] Implement Last Active Admin Lockout Protection and self-demotion / self-deactivation prevention in `app/controllers/UserController.php`.
- [x] Extend workforce models (`User.php`, `Leave.php`, `MySqlLeaveApprovalGuard.php`) and REST APIs (`EmployeeController`, `DepartmentController`, `PositionController`, `LeaveController`) to permit Admin authority.
- [x] Extend WebSocket real-time event pipeline (`Identity.php`, `ChannelPolicy.php`, `EventAuthorizer.php`, `PdoRealtimeStore.php`, `RealtimeTicketService.php`) for Admin channels and leave alerts.
- [x] Update frontend views: 3-portal login screen with switcher tab, Admin sidebar branding, Admin dashboard console, and User Management views.
- [x] Author comprehensive test suite (`tests/Feature/AdminRoleRbacTest.php`) bringing PHPUnit suite to 600 passing tests.
- [x] Document Architectural Decision Record ADR-012 in `docs/02-technical/decisions/DECISIONS.md`.

### Phase 12: Production Deployment & VPS Release (`PENDING`)
- [ ] Provision production cloud VPS (Ubuntu 24.04 LTS).
- [ ] Configure production domain DNS and Let's Encrypt automated SSL renewal.
- [ ] Set up production SMTP transport (SendGrid / AWS SES) for MFA email delivery.
- [ ] Configure Systemd service units for Ratchet WebSocket daemon and Outbox worker.
- [ ] Execute production smoke tests and verify zero regression.

---

## 3. Known Issues & Technical Debt

| Item ID | Description | Impact | Priority | Status |
|---|---|---|---|---|
| **TECH-001** | Legacy PHPCS baseline has 236 pre-existing errors in older scaffolding. | Non-blocking (0 new violations enforced). | Low | `ACCEPTED_DEBT` |
| **TECH-002** | 3 tests in PHPUnit are conditionally skipped when external SMTP or display is absent. | Non-blocking (592 passing). | Low | `MONITORED` |
| **TECH-003** | Docker Compose on Windows sandbox requires user approval for named pipe access. | Operational tooling only. | Medium | `RESOLVED_WORKAROUND` |

---

## 4. Immediate Actionable Tasks

1. **Complete Authoritative Documentation Generation:**
   * Create `MEMORY.md` (compact AI context primer).
   * Create `docs/security/SECURITY.md`, `docs/database/DATABASE.md`, `docs/testing/TESTING.md`, `docs/api/API.md`, `docs/decisions/DECISIONS.md`.
   * Create `docs/deployment/DEPLOYMENT.md`, `docs/troubleshooting/TROUBLESHOOTING.md`, `docs/glossary/GLOSSARY.md`.
   * Update `README.md` and `docs/README.md`.
2. **Run Post-Consolidation Validation:**
   * Verify all Markdown links and anchor tags.
   * Run `git status` to ensure **no PHP/JS/CSS/SQL/Docker/ENV files were modified**.
   * Run `php bin/check-phpcs-baseline.php` to ensure 0 new code sniffer violations.
   * Run `./vendor/bin/phpunit` to confirm 100% test suite health.

