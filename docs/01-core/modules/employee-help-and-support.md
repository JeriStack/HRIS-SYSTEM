# NEXORA HR — Employee Help & Support and Complaint Management Module Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Specification Baseline:** Comprehensive Development Plan v3.1 (aligned with ADR-012 3-Role RBAC)  
**Document Type:** Approved Scope & Architecture Addendum  
**Date:** November 2026  

---

## 1. Purpose & Scope Addendum

Under **Comprehensive Development Plan v3.1**, this document defines the approved functional, security, database, UI/UX, and operational specifications for the **Employee Help & Support and Complaint Management Module**.

The module enables authenticated employees to submit general HR/technical/leave/profile support requests and confidential workplace complaints, communicate with HR via threaded replies and secure attachments, and track lifecycle progress end to end — while equipping authorized HR officers with a triaged Support Inbox, internal note isolation, permission-bounded confidential complaint handling, conflict-of-interest (COI) exclusions, and automated escalation safeguards.

### Role Model Compliance (ADR-012)
* Strictly preserves the existing three application roles: `admin`, `hr`, and `employee`.
* **No additional Admin application role is introduced.**
* Confidential complaint clearance is enforced **within the existing `hr` and `admin` roles** via granular RBAC capabilities (`support.confidential_handle`, `support.manage_handlers`) combined with explicit user-level designation in `support_ticket_handlers` and mandatory COI subject exclusion checks.

---

## 2. Phase 1 Inspection Baseline

Prior to design, the existing NEXORA HR codebase was audited to ensure reuse of established patterns:
* **Routing & Middleware:** `core/Router.php`, `public/index.php` (`AuthMiddleware`, `RoleMiddleware`, `PermissionMiddleware`, `CsrfMiddleware`).
* **RBAC & Governance:** `core/PermissionRegistry.php`, `core/AuthorizationService.php`, `core/Permission.php`, `app/controllers/AdminController.php`.
* **Confidentiality & Document Security Blueprints:** `app/services/ConfidentialLeaveService.php` and `app/services/LeaveDocumentService.php` (`storage/uploads/.htaccess` non-public storage, `finfo` + magic-byte validation, SHA-256 checksums, randomized filenames, authorized `readfile()` streaming).
* **Notification & Real-Time Pipeline:** `app/models/Notification.php`, `app/realtime/EventAuthorizer.php`, `app/realtime/NotificationPayload.php`, `app/realtime/PdoRealtimeStore.php`, `app/controllers/DashboardController.php::resolveNotificationDestination()`.
* **Audit Logging:** `core/AuditService.php`, `app/models/ActivityLog.php`.
* **UI Shell & Theme System:** `app/views/layouts/hr.php`, `app/views/partials/sidebar.php`, `app/views/dashboard/employee.php`, `public/assets/css/style.css`, `public/assets/css/components.css` (Light Mode & Dark Mode `[data-theme="dark"]` tokens).
* **Database & Parity Rules:** `database/schema.sql`, `docker/mysql/init/01-schema.sql`, `database/migrations/`, `tests/Unit/Database/InitSchemaParityTest.php`.

---

## 3. Support Request Categories & Ticket Model

### General Support Categories
1. `General Inquiry`
2. `Technical Issue`
3. `Leave Concern`
4. `Employee Profile Correction`
5. `Workplace Complaint` *(automatically routes into confidential complaint protections & disclosure)*
6. `Policy Clarification`
7. `Other`

### Dedicated Workplace Complaint Categories
1. `Workplace Conduct`
2. `Employee Relations`
3. `Workplace Policy Concern`
4. `Discrimination or Harassment`
5. `Unprofessional Behavior`
6. `Other Workplace Concern`

### Ticket Reference Generation
* Server-generated format: `SUP-YYYY-NNNNN` (e.g., `SUP-2026-00001`).
* Generated atomically inside a database transaction via `support_ticket_sequences` (`SELECT ... FOR UPDATE`) combined with `UNIQUE (ticket_ref)` on `support_tickets`.
* Employee identity (`employee_id`, `submitted_by_user_id`) is derived strictly from the authenticated server session; client-supplied employee identifiers are ignored/rejected.

---

## 4. Non-Anonymous Accountability & Confidentiality Controls

### Disclosure Policy
* All submissions are authenticated through the employee's NEXORA HR account; the system does **not** claim anonymous reporting.
* Prior to submitting a workplace complaint, the interface displays clear guidance specifying:
  * Why identity is recorded with the submission.
  * Who is authorized to access confidential complaint details and attachments.
  * How conflict-of-interest (COI) exclusions work when an employee names a colleague or HR officer as a subject.
  * That confidentiality is enforced strictly through role/handler authorization and audit logging (without promising absolute confidentiality beyond system controls).

### Permission-Bounded Confidential Access & Conflict-of-Interest (COI)
1. **No Blanket HR Access:** General `hr` role membership allows handling standard support requests (`support.inbox_view`), NOT confidential complaints. Viewing/handling confidential complaints requires active designation in `support_ticket_handlers` alongside `support.confidential_handle` (or active escalation oversight by a non-conflicted Administrator).
2. **Conflict-of-Interest (COI) Self-Exclusion:** If a viewer's `employee_id` equals the ticket's `subject_employee_id`, access is unconditionally blocked across:
   * HR Support Inbox rows and overview metrics (`Confidential Complaints Requiring Attention`).
   * Direct ticket inspection routes (`/hr/support/{id}`).
   * Handler assignment and status/reply mutations.
   * Secure attachment downloads.
   * In-app and real-time notifications.
3. **Escalation Policy:** When a confidential complaint is submitted (or loses all eligible handlers due to COI) and zero non-conflicted authorized `hr` handlers exist, the ticket is automatically flagged `escalated_flag = 1` with an explicit escalation reason and routed to non-conflicted Admin oversight; if no unconflicted internal handler exists at all, it remains flagged for external/executive review and masked from all conflicted accounts.
4. **Information Isolation:**
   * Sensitive complaint descriptions, subject names, and attachment names are never shown in Employee Dashboard widgets, ordinary notifications, or public audit descriptions.
   * All confidential views, denials, COI blocks, downloads, and handler assignments are recorded in `support_sensitive_access_logs` and sanitized metadata events in `activity_logs`.

---

## 5. Ticket Lifecycle & Transition Rules

Supported statuses:
* `Submitted` &rarr; `Under Review` | `Closed` *(HR/authorized handler only)*
* `Under Review` &rarr; `Waiting for Employee` | `Resolved` | `Closed`
* `Waiting for Employee` &rarr; `Under Review` *(manual by HR, or automatic when employee replies)* | `Resolved` | `Closed`
* `Resolved` &rarr; `Closed` *(by HR, or explicit employee confirmation `confirm-close`)* | `Under Review` *(employee reopen permitted within 14 calendar days with reason; HR/authorized handler reopen permitted anytime)*
* `Closed` &rarr; `Under Review` *(HR/authorized handler reopen only with remarks; employees cannot reopen closed tickets or directly mark non-resolved tickets as Resolved/Closed)*

Every transition records `from_status`, `to_status`, `changed_by_user_id`, `remarks`, and timestamp in `support_status_history`.

---

## 6. Threaded Conversation & Attachment Security

* **Messages (`support_messages`):** Chronologically ordered thread containing `initial_submission`, `hr_response`, `employee_reply`, `info_request`, `internal_note`, and `status_change` entries. Historical messages are immutable to prevent unauthorized editing/deletion. Entries with `is_internal_note = 1` are strictly filtered out from employee queries and views.
* **Attachments (`support_attachments`):**
  * Stored outside webroot under `storage/uploads/support_attachments/YYYY/MM/`.
  * Validated for upload errors, size limit (`config/support.php`, default 5 MB, up to configured per-submission max files), MIME type (`finfo`), magic bytes (PDF, JPG, PNG, WebP, DOCX/XLSX/TXT safe allowlist where supported without executable risk — HTML/SVG/PHP/scripts strictly blocked), and randomized storage filenames (`supdoc-...`).
  * Retrieved solely via `/support/attachments/{id}` after ticket access, COI, and internal-only checks, streamed via `readfile()` with `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-store, max-age=0`.

---

## 7. Database Entities

Tables defined identically in `database/migrations/2026_11_17_create_support_and_complaint_module.sql`, `database/schema.sql`, and `docker/mysql/init/01-schema.sql`:
1. `support_ticket_sequences`
2. `support_tickets`
3. `support_ticket_handlers`
4. `support_messages`
5. `support_attachments`
6. `support_status_history`
7. `support_assignments`
8. `support_sensitive_access_logs`

---

## 8. Integration Surface & Acceptance Criteria

* **Employee Navigation & Dashboard:** Sidebar link `Help & Support` (`/support`), compact Employee Dashboard widget (`Submit Request`, `Submit Complaint`, open count, `View My Requests`, max 3 non-sensitive recent requests).
* **Support Center (`/support`):** Hub guidelines, searchable & filterable `My Support Requests`, Create Support Request (`/support/create`), Create Workplace Complaint (`/support/complaints/create`), Ticket Detail (`/support/{id}`), replies, permitted attachments, reopen/confirm-close, authorized attachment download (`/support/attachments/{id}`).
* **HR Support Inbox (`/hr/support`):** Overview counters (`Confidential Complaints Requiring Attention` visible only to authorized confidential handlers), searchable/filterable inbox table, ticket workspace (`/hr/support/{id}`) with separate reply vs internal note workflows, priority/status management, handler assignment/reassignment, and Confidential Handler Registry (`/hr/support/handlers`).
* **Verification:** Zero PSR-12 regressions, PHPStan level-1 clean, comprehensive PHPUnit unit/feature/security test coverage against `hris_test`, Selenium E2E flows, and Light/Dark Mode WCAG contrast + responsive layout inspection.
