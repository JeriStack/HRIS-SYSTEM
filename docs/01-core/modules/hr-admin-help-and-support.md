# NEXORA HR — Admin Least-Privilege Governance & HR-to-Admin Help & Support Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Specification Baseline:** Comprehensive Development Plan v3.2 (3-Role Least-Privilege RBAC & HR-to-Admin Support)  
**Document Type:** Approved Architecture & Scope Specification  
**Date:** November 2026  

---

## 1. Purpose & Architectural Scope

This specification documents two production-grade enhancements in **NEXORA HR**:
1. **Least-Privilege Three-Role Hardening (`admin`, `hr`, `employee`)**: Replaces any blanket Administrator wildcard bypass with explicit, least-privilege capabilities so Administrators govern accounts, roles, security, system settings, and HR support tickets without unrestricted access to confidential HR leave documents, unmasked personal employee contact details, HR operational mutations, or Employee self-service tickets.
2. **HR-to-Admin Help & Support Module (`/hr/help*` & `/admin/support*`)**: Equips HR personnel with a dedicated channel to report technical issues, system errors, access problems, and operational concerns directly to System Administrators using non-guessable references (`HR-SUP-YYYY-XXXXXXXX`), isolated from Employee Help & Support (`SUP-YYYY-NNNNN`) and Public Pre-Login Support (`AUTH-YYYY-NNNNN`).

---

## 2. Updated Role and Permission Overview

| Capability | Admin (`admin`) | HR (`hr`) | Employee (`employee`) |
| :--- | :---: | :---: | :---: |
| **Manage system accounts and permissions** | ✓ | — | — |
| **Manage HR records** | Restricted *(read-only directory oversight; personal PII masked)* | ✓ | Own permitted records |
| **Manage leave requests** | Restricted *(read-only non-confidential oversight; no approve/reject/confidential docs)* | ✓ | Own requests |
| **Submit HR support tickets** | — | ✓ | — |
| **Manage HR support tickets** | ✓ | Own tickets | — |
| **View Admin internal notes** | ✓ | — | — |
| **Employee Help & Support** | — *(except escalated COI governance)* | — *(handles via `/hr/support`)* | ✓ |
| **System administration** | ✓ | — | — |

### Granular `hr_support.*` Permissions (`Core\PermissionRegistry`)
* `hr_support.submit` (`hr`): Submit technical and operational support tickets to Administrators.
* `hr_support.view_own` (`hr`): View own HR-to-Admin tickets, public status history, and Admin responses.
* `hr_support.reply_own` (`hr`): Send follow-up replies, attach files, confirm resolution, or request reopening on own HR tickets.
* `hr_support.admin_inbox` (`admin`): Access the Admin Support Management Inbox (`/admin/support`) and live support KPI metrics.
* `hr_support.admin_manage` (`admin`): Assign active Administrators, change priority/status, respond to HR, resolve, close, and reopen tickets.
* `hr_support.internal_notes` (`admin`): Create and read private Admin-only internal investigation notes and internal-only attachments.

---

## 3. HR Dashboard — Help & Support (`/hr/help`)

### 3.1 Ticket Submission (`GET /hr/help/create`, `POST /hr/help/tickets`)
* **Fields:** Subject (`5–200` chars), Issue Category, Priority (`Low`, `Medium`, `High`, `Critical`), Detailed Description (`15–5,000` chars), and optional supporting attachments (up to `3` files, max `5 MB` each; `PNG`, `JPG`, `WEBP`, `PDF`, `TXT`).
* **Categories:** `System Error`, `Login and Authentication`, `Account and Access Problems`, `Dashboard or UI Issue`, `Employee Management Issue`, `Leave Management Issue`, `Notification or Email Issue`, `Performance or System Availability`, `Data Discrepancy`, `Other`.
* **Reference Generation:** CSPRNG-backed non-guessable reference `HR-SUP-YYYY-XXXXXXXX` (8 unambiguous alphanumeric characters). Internal database primary keys are never accepted as public route references.
* **Pre-Submission Confirmation:** Accessible inline validation paired with a native `<dialog>` confirmation review modal prior to submission.

### 3.2 My Support Tickets & Conversation (`GET /hr/help`, `GET /hr/help/tickets/{ref}`)
* Displays only tickets submitted by the authenticated HR account (`submitted_by_user_id`), filterable by search query, status, category, and submission date range.
* Displays public messages, non-internal attachments (`/hr/help/attachments/{id}`), and transition history.
* **Strict Backend Isolation:** `HrSupportTicket::getMessages($id, false)` and `getAttachments($id, false)` omit `is_internal_note = 1` and `is_internal_only = 1` at the SQL layer for both HTML and JSON responses.

### 3.3 Controlled Status Lifecycle & Reopening Policy
* **Statuses:** `Submitted` &rarr; `Under Review` &rarr; `In Progress` &harr; `Waiting for HR` &rarr; `Resolved` &rarr; `Closed`.
* When a ticket is `Waiting for HR` and the owning HR user posts a reply, the ticket automatically advances back to `In Progress` (if assigned) or `Under Review` (if unassigned).
* When `Resolved`, HR can either **Confirm Resolution** (`Resolved` &rarr; `Closed`) or **Request Reopen** (`Resolved` &rarr; `Under Review`) within `14` calendar days.
* Once `Closed`, HR self-reopening is locked (`HTTP 409`); only an Administrator may reopen a `Closed` ticket.

---

## 4. Admin Dashboard — Support Management (`/admin/support`)

* **Live Support Summary Metrics (`HrSupportTicket::getAdminDashboardStats()`):**
  1. `total_open` (`Submitted`, `Under Review`, `In Progress`, `Waiting for HR`)
  2. `newly_submitted` (`Submitted`)
  3. `critical_and_high` (Open tickets with `High` or `Critical` priority)
  4. `awaiting_hr` (`Waiting for HR`)
  5. `in_progress` (`In Progress`)
  6. `recently_resolved` (`Resolved` or `Closed` within the last 7 days)
* **Triage & Communication (`GET /admin/support/tickets/{ref}`):**
  * Assign or reassign to active `admin` accounts (`POST /admin/support/tickets/{ref}/assign`).
  * Update priority, status, resolution summary, and optional public transition remarks (`POST /admin/support/tickets/{ref}/triage`).
  * Post HR-visible replies, information requests (`Waiting for HR`), or private Admin-only internal notes (`POST /admin/support/tickets/{ref}/respond`).

---

## 5. Notifications, Audit Trail & Database Schema

* **Notifications (`HrSupportNotificationService`):**
  * Dispatches in-app `notifications` rows, real-time WebSocket events (`hr_support.*` authorized by `EventAuthorizer`), and PHPMailer/SMTP/Mailpit emails.
  * **Email Redaction:** Emails include only `ticket_ref`, enum metadata (`Category`, `Priority`, `Status`), high-level notice, and a direct link to `/admin/support/tickets/{ref}` or `/hr/help/tickets/{ref}` — never ticket descriptions, reply bodies, or internal notes.
  * **Non-Blocking Delivery:** Notification and SMTP execution run after database `COMMIT` inside `try/catch` guards so transport errors never roll back committed ticket transactions.
* **Audit Logging (`Core\AuditService`):** Logs `HR_SUPPORT_TICKET_CREATED`, `HR_SUPPORT_TICKET_ASSIGNED`, `HR_SUPPORT_PRIORITY_CHANGED`, `HR_SUPPORT_STATUS_CHANGED`, `HR_SUPPORT_ADMIN_RESPONDED`, `HR_SUPPORT_INFO_REQUESTED`, `HR_SUPPORT_INTERNAL_NOTE_ADDED` (redacted body), `HR_SUPPORT_TICKET_RESOLVED`, `HR_SUPPORT_TICKET_CLOSED`, and `HR_SUPPORT_TICKET_REOPENED`.
* **Normalized InnoDB Tables (`2026_11_20_create_hr_to_admin_support_module.sql`):**
  * `hr_support_tickets`
  * `hr_support_ticket_messages`
  * `hr_support_ticket_attachments`
  * `hr_support_ticket_assignments`
  * `hr_support_ticket_history`
