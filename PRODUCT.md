# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Employees (Self-Service Workspace):** Organization staff who inspect their personal employment profile, monitor annual leave entitlements (`allocated_days`, `used_days`, `remaining_days`), file time-off applications with automatic business-day calculation, cancel pending requests, and track approval notifications.
- **HR Officers (HR Operational Portal):** Human Resources personnel who manage the employee directory, organizational departments and job positions, leave classifications and quota allocations, the pending leave adjudication queue (approve/reject with atomic balance deduction), and HR operational reports.
- **System Administrators (Admin Governance Portal):** Privileged administrators who oversee user account provisioning, database-backed Role-Based Access Control (RBAC), authentication/MFA/step-up security policies, audit logs, security events, notification channels, and runtime health.

## Product Purpose

**NEXORA HR** is a centralized enterprise Human Resource Information System (HRIS) and Leave Management System that unifies employee record management, policy-driven leave workflows, real-time notifications, and auditable administrative governance into a single role-aware web platform. Success means employees can check balances and file accurate leave requests in seconds, HR officers can adjudicate requests with zero balance discrepancies, and administrators can verify security and compliance across every state-changing action.

## Positioning

Unlike generic CRUD HR templates, NEXORA HR pairs production-grade enterprise HR & leave governance (strict server-side RBAC, ownership enforcement, step-up authentication, and real-time WebSocket notifications) with purpose-built custom PHP Data Structures & Algorithms (Singly Linked Lists for chronological leave history, FIFO Queues for pending leave review, Stacks for action undo/history, Hash Tables for fast lookup, and Merge/Quick/Binary Search algorithms) designed for both rigorous academic defense and production reliability.

## Operating Context

- **Runtime & Infrastructure:** PHP 8.5 custom MVC architecture backed by MySQL 8.0, served via Nginx + Apache/PHP-FPM in Docker Compose (or local XAMPP), with a Ratchet WebSocket server (`hris_realtime`), background queue worker (`hris_worker`), and scheduled cron daemon (`hris_scheduler`), plus Prometheus and Grafana observability.
- **Evaluation & Usage Scene:** Used daily in desktop and mobile browsers for operational HR tasks and evaluated in academic/technical defense demonstrations where data accuracy, RBAC security boundaries, and UI craftsmanship are inspected side by side.

## Capabilities and Constraints

- **Three Approved Roles:** Strictly `Admin`, `HR`, and `Employee`. Public Admin registration is prohibited; initial Admin provisioning uses a controlled setup workflow, and the last active Admin account is protected.
- **Server-Side Ownership & RBAC:** Every route enforces server-side permission and record-ownership checks (`NFR-SEC-004`). Employees can access only their own profile, leave balances, leave requests, and notifications.
- **Leave Business Rules:** Leave duration is calculated on business days (Monday–Friday, excluding weekends). Filing requires sufficient remaining balance (`remaining_days`), prevents overlapping dates, enters a `Pending` state for HR review, and allows employee cancellation only while `Pending`.
- **Frontend Stack Constraint:** Native PHP server-rendered views (`app/views/`), vanilla CSS custom properties (`public/assets/css/`), and lightweight progressive-enhancement vanilla JavaScript (`public/assets/js/`). No external CSS/JS UI frameworks or external web font dependencies.

## Brand Commitments

- **Brand Name:** NEXORA HR
- **Tagline:** People. Processes. Progress.
- **Voice & Demeanor:** Clear, authoritative, calm, and concise enterprise operational language—free of decorative emojis, redundant role badges, or developer-facing jargon on end-user screens.

## Evidence on Hand

- **Database Schema & Seed Data:** Authoritative relational schema and seed records in `database/schema.sql`, `database/migrations/`, and `database/seed.sql` (including sample accounts `EMP001`–`EMP007`, leave types, leave balances, and leave requests).
- **Automated Verification Suite:** 669 PHPUnit unit, integration, and feature tests (`tests/`), PSR-12 coding standard baseline (`bin/check-phpcs-baseline.php`), and OpenAPI documentation (`docs/api/openapi.yaml`).

## Product Principles

1. **Authoritative Data Clarity:** Every balance, duration, and status displayed in the interface must reconcile transparently with the underlying database ledger—never fabricate or duplicate conflicting figures.
2. **Role-Scoped Focus:** Each workspace shows only the actions and context relevant to that user's role; Employee self-service screens remain focused on personal time off, profile accuracy, and timely updates.
3. **One Action, One Signal:** Primary workflows (such as filing a leave request or reviewing a pending application) have a single unmistakable entry point per screen and immediate validation feedback.
4. **Defense-Ready Integrity:** Security controls (CSRF tokens, POST-only state mutations, ownership checks, and audit trails) are non-negotiable across every UI improvement.

## Accessibility & Inclusion

- **Standard:** WCAG 2.2 AA compliance across both default Light Mode and optional Dark Mode (`[data-theme="dark"]`).
- **Requirements:** Minimum 4.5:1 text contrast ratio, visible `:focus-visible` keyboard outlines (`2px solid`), dual-channel status indicators (color paired with text and icon/dot), `prefers-reduced-motion` support, and accessible form error announcements (`aria-describedby` + `role="alert"`).

