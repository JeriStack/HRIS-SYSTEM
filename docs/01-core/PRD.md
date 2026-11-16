# NEXORA HR — Product Requirements Document (PRD)

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Production Specification  
**Date:** November 2026  
**Document Owner:** Repository Maintainer & Engineering Architecture Team  

---

## 1. Executive Summary & Vision

**NEXORA HR** (*People. Processes. Progress.*) is an enterprise-grade Human Resource Information System (HRIS) and Leave Management Platform. It delivers an integrated, transaction-safe, and highly accessible solution for workforce directory administration, organizational position governance, leave entitlement tracking, and multi-factor authentication.

The platform is engineered to eliminate error-prone manual spreadsheets, prevent race conditions in leave balance deductions, and provide responsive self-service portals for both HR professionals and general workforce employees.

---

## 2. Problem Statement & Business Goals

### 2.1 Problem Statement
Spreadsheet-driven HR processes and legacy web applications suffer from:
1. **Concurrency and Balance Corruption:** Simultaneous leave approvals or overlapping requests can result in negative balances, double-allocations, or race conditions.
2. **Security Vulnerabilities:** Inadequate session lifecycle management, weak single-factor passwords, and missing ownership checks expose sensitive Personally Identifiable Information (PII).
3. **Delayed Information Flow:** Lack of real-time communication forces employees to manually refresh pages or wait for asynchronous emails to confirm approval statuses.
4. **Complex and Convoluted Roles:** Conflating administrative, HR, and managerial roles creates privilege escalation risks.

### 2.2 Strategic Objectives
* **100% Transaction Integrity:** Guarantee ACID compliance for all leave deductions, cancellations, and balance initializations using pessimistic database locking.
* **Streamlined 3-Role Governance:** Enforce a strict three-role model (`admin`, `hr`, and `employee`), ensuring strict separation of system-wide governance, HR operational management, and employee self-service.
* **Instantaneous Notifications:** Broadcast critical workflow changes in sub-100ms through a dedicated WebSocket daemon backed by a Transactional Outbox.
* **Robust Enterprise Security:** Provide defense-in-depth through mandatory Email OTP / TOTP Multi-Factor Authentication (MFA), continuous session state verification, step-up authentication, last-active-admin protection, and synchronized CSRF defenses.

---

## 3. Target Users & Application Roles

NEXORA HR strictly enforces **three application roles**: **`admin`**, **`hr`**, and **`employee`**.

| Role | Target Audience | Primary Responsibilities | Scope of Access |
|---|---|---|---|
| **`admin`** | System Administrators, Security Officers, IT Directors | System-level administration, user account provisioning, role/permission governance, HR support ticket management (`/admin/support*`), system error review, security audit monitoring, and system configuration. | Dedicated Admin Console (`/admin/dashboard`). Least-privilege restricted read-only oversight of non-confidential workforce/leave directories; restricted from HR operational mutations, confidential leave documents, and Employee self-service portals. |
| **`hr`** | HR Officers, Personnel Managers, Department Heads | Operational workforce management, department/position configuration, employee onboarding, leave approval queue processing, Employee Support Inbox (`/hr/support*`), and reporting technical/system issues to Admin (`/hr/help*`). | Company-wide HR operational visibility (`employees.*`, `leave.*`, `support.*`, `hr_support.submit/view_own/reply_own`). Prohibited from user role assignment, Admin provisioning, Admin internal notes, or system settings. |
| **`employee`** | Active Workforce Members, Individual Contributors | Personal profile view, leave balance tracking, submitting leave requests, cancelling pending requests, Employee Help & Support (`/support*`), viewing personal notification stream. | Restricted strictly to own records (`ownership validation`). Cannot view other employees' records, HR support tickets, or organizational management consoles. |

---

## 4. Product Scope

### 4.1 In-Scope Capabilities
* **Workforce Directory Management:** Complete employee lifecycle management (creation, personal/job record editing, status deactivation/reactivation).
* **Organizational Structure:** Department and position CRUD operations with referential integrity protection.
* **Leave Management Engine:** Leave filing, FIFO pending queue, single-request lock processing, automatic business day calculation (excluding weekends), cross-year request restrictions, and overlap detection.
* **Leave Entitlements & Balances:** Year-based quota tracking (Vacation, Sick, Emergency, Maternity, Paternity, Bereavement) with missing-balance automatic initialization.
* **Multi-Factor Authentication (MFA):** Mandatory 6-digit cryptographic Email OTP verification, optional RFC 6238 TOTP authenticator app support, and Step-Up re-authentication for high-impact actions.
* **Real-time Event Streaming:** WebSocket-driven notification stream, dynamic unread badge count, and automatic dashboard table synchronization.
* **Audit Logging & Analytics:** Tamper-evident activity logs recording IP address, user agent, actor, action type, target entity, and timestamp.
* **Data Export & Reporting:** Real-time workforce and leave data exports formatted in standard CSV and JSON.
* **Interactive API Specification:** Integrated OpenAPI 3.0 / Swagger UI documentation for programmatic integration.

### 4.2 Out-of-Scope Capabilities
* Biometric hardware clock-in/clock-out capture.
* Payroll computation, tax calculation, and payslip generation.
* Candidate recruitment, ATS applicant pipelines, and resume parsing.
* Performance review appraisal forms and 360-degree feedback cycles.
* Multi-tenant billing and multi-organization tenant separation.

---

## 5. Functional Requirements (FR)

### 5.1 Workforce & Organization Management

#### FR-001: Employee Onboarding & Record Lifecycle
* The system shall allow HR officers to register new employee records with mandatory attributes: Employee Number (`employee_no`), First Name, Last Name, Birth Date, Gender, Date Hired, Department ID, Position ID, and Work Email.
* Middle Name, Phone Number, and Residential Address shall be optional.
* Unique constraints shall be enforced on `employee_no` and `email`.
* Deactivating an employee record shall automatically deactivate their linked system user account and invalidate active sessions on their next request.

#### FR-002: Department & Position Governance
* The system shall allow HR officers to manage departments and positions.
* Deletion of a department or position shall be blocked (`ON DELETE RESTRICT`) if active employee records reference the entity.

### 5.2 Leave Management Engine

#### FR-003: Leave Request Submission
* Employees shall be permitted to submit leave requests only for valid active leave types.
* The system shall automatically compute the number of leave days by counting only **business days (Monday through Friday)** between `start_date` and `end_date`, inclusive. Saturdays and Sundays shall be excluded from deduction.
* The submission shall be rejected if:
  * `end_date` is earlier than `start_date`.
  * The requested date range crosses multiple calendar years (requests must fall entirely within the current calendar year).
  * The requested date range overlaps with any existing `Pending` or `Approved` request for the same employee.
  * The requested business days exceed the employee's current `remaining_days` for the selected leave type.

#### FR-004: Leave Approval & Balance Processing
* The HR leave processing queue shall display pending requests sorted in First-In, First-Out (**FIFO**) order by creation timestamp.
* When an HR officer approves a request, the system shall execute the operation inside an atomic database transaction using pessimistic locking:
  1. Acquire an exclusive row lock (`SELECT ... FOR UPDATE`) on the `leave_requests` record to prevent concurrent double-approvals.
  2. Verify that the request status is still `Pending`.
  3. Acquire an exclusive row lock on the corresponding `leave_balances` record for the employee, leave type, and calendar year.
  4. Verify that `remaining_days >= number_of_days`.
  5. Atomically increment `used_days` and decrement `remaining_days`.
  6. Transition the request status to `Approved` and record `approved_by` (HR user ID) and `approved_at` timestamp.
  7. Insert an audit log entry in `activity_logs`.
  8. Insert a notification for the employee in `notifications` and an event in `outbox_events`.
  9. Commit the transaction. If any step fails, roll back completely.

#### FR-005: Leave Rejection & Cancellation
* HR officers may reject pending leave requests by providing a mandatory or optional rejection reason. Rejection does not modify leave balances.
* Employees may cancel their own `Pending` leave requests at any time before HR review.
* If an employee cancels a previously `Approved` leave request, the system shall execute an atomic transaction acquiring a row lock on `leave_balances`, returning the deducted days from `used_days` to `remaining_days`, and logging the cancellation.

#### FR-006: Leave Balance Tracking & Missing Balance Initialization
* Each employee shall have annual quota allocations across defined leave types:
  * Vacation Leave: 15 days
  * Sick Leave: 10 days
  * Emergency Leave: 5 days
  * Maternity Leave: 60 days
  * Paternity Leave: 7 days
  * Bereavement Leave: 3 days
* If an active employee record exists without an initialized balance record for the current year, the system shall safely initialize default balances on-demand rather than failing or returning null.

### 5.3 Authentication, Security & Real-Time Events

#### FR-007: Multi-Factor Authentication (MFA)
* All user sign-ins shall require two steps:
  1. **Primary Authentication:** Username or email and password verified via `password_verify()` against a cost-12 Bcrypt hash.
  2. **Second-Factor Verification:** Generation and delivery of a cryptographically secure 6-digit numeric OTP to the user's verified email, valid for 10 minutes (600 seconds).
* The system shall support time-based one-time password (TOTP RFC 6238) authenticators as an alternative MFA channel.
* Maximum OTP verification attempts shall be capped at 5 per code; exceeding attempts invalidates the code.

#### FR-008: Role-Based Access Control & Ownership Validation
* Every HTTP route shall be protected by middleware enforcing authentication (`AuthMiddleware`) and role authorization (`RoleMiddleware`).
* Employee portal endpoints (`/employee/*`) shall enforce ownership: if an employee attempts to query or access records belonging to another employee ID, the system shall reject the request with HTTP 403 Forbidden.

#### FR-009: Real-time Event Distribution & Outbox Processing
* The system shall maintain an event-driven notification pipeline using a Ratchet WebSocket server daemon (:8090).
* Domain events (`leave.applied`, `leave.approved`, `leave.rejected`, `employee.profile_updated`) shall be written to an `outbox_events` table within the primary transaction.
* An outbox worker process or trigger shall poll and dispatch events to the WebSocket server via an internal loopback token.
* Connected browsers shall receive notifications and automatically refresh affected dashboard tables without full-page reloads.

#### FR-010: Step-Up Authentication
* High-impact HR operations (e.g., employee termination or bulk leave adjustments) shall trigger Step-Up Authentication, requiring the HR user to re-enter their password or confirm an OTP if their last verification was more than 15 minutes prior.

---

## 6. Non-Functional Requirements (NFR)

### 6.1 Security Requirements (NFR-SEC)
* **NFR-SEC-001:** Passwords must be hashed using Bcrypt (`PASSWORD_BCRYPT`) with an algorithmic cost factor of 12.
* **NFR-SEC-002:** CSRF protection must be enforced on all state-altering HTTP methods (`POST`, `PUT`, `DELETE`) using the Synchronizer Token Pattern (`CsrfMiddleware`).
* **NFR-SEC-003:** Session IDs must be regenerated (`session_regenerate_id(true)`) upon successful authentication to prevent session fixation attacks.
* **NFR-SEC-004:** On every authenticated HTTP request, `Session::verifyAuthenticatedAccountState()` must validate that the user and linked employee remain `Active`, the role has not changed, and the cryptographic security fingerprint matches. Deactivated accounts must be terminated immediately.
* **NFR-SEC-005:** Sensitive Personally Identifiable Information (PII) such as phone numbers, home addresses, and dates of birth must not be broadcast over WebSocket payloads.
* **NFR-SEC-006:** All SQL queries must use prepared statements with explicit parameter binding to eliminate SQL Injection risks.
* **NFR-SEC-007:** HTML output must be sanitized using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` via the `e()` helper function to prevent Cross-Site Scripting (XSS).

### 6.2 Performance & Scalability (NFR-PERF)
* **NFR-PERF-001:** Standard database read queries must complete in under 50ms under typical operating conditions.
* **NFR-PERF-002:** The application response time for dashboard page loads must remain under 200ms at the 95th percentile.
* **NFR-PERF-003:** WebSocket notification delivery from outbox event emission to client receipt must occur in under 100ms.
* **NFR-PERF-004:** Database tables must employ composite indexes on frequently filtered columns (e.g., `(user_id, is_read)` on `notifications`, `(employee_id, year)` on `leave_balances`).

### 6.3 Reliability & Transactional Integrity (NFR-REL)
* **NFR-REL-001:** All balance deductions and restorations must execute in InnoDB database transactions with a pessimistic locking strategy (`SELECT ... FOR UPDATE`) to guarantee zero race conditions under concurrent approvals.
* **NFR-REL-002:** The system must guarantee at-least-once notification delivery via the Transactional Outbox pattern; network transport failures during notification dispatch must not roll back committed business transactions.
* **NFR-REL-003:** In the event of a WebSocket disconnect, the client-side JavaScript must employ exponential backoff reconnection logic while continuing to function via standard HTTP polling.

### 6.4 Usability & Accessibility (NFR-A11Y)
* **NFR-A11Y-001:** The user interface must adhere to WCAG 2.1 AA accessibility guidelines, maintaining a minimum color contrast ratio of 4.5:1 for standard text and 3:1 for large text.
* **NFR-A11Y-002:** All interactive controls (modals, dropdowns, navigation toggles, forms) must be fully navigable and operable via keyboard commands alone.
* **NFR-A11Y-003:** Form inputs must possess explicit associated `<label>` elements, `aria-required`, and descriptive error announcements (`role="alert"`).

---

## 7. Requirements Traceability Matrix (RTM)

| Requirement ID | Module / Feature | Implemented Components | Verification Test Suite | Status |
|---|---|---|---|---|
| **FR-001** | Employee Lifecycle | `EmployeeController`, `Employee`, `User` | `EmployeeCrudTest`, `EmployeeServiceTest` | **VERIFIED** |
| **FR-002** | Department/Position | `DepartmentController`, `Department`, `Position` | `DepartmentManagementTest`, `PositionTest` | **VERIFIED** |
| **FR-003** | Leave Submission | `LeaveController`, `LeaveRequestService` | `LeaveApplicationTest`, `LeaveOverlapTest` | **VERIFIED** |
| **FR-004** | Leave FIFO Approval | `Leave`, `LeaveController`, `LeaveApprovalService` | `LeavePessimisticLockTest`, `LeaveApprovalTest` | **VERIFIED** |
| **FR-005** | Leave Cancellation | `LeaveController`, `Leave` | `LeaveCancellationTest`, `LeaveRollbackTest` | **VERIFIED** |
| **FR-006** | Balance Initializer | `LeaveBalanceService`, `Leave` | `LeaveBalanceSyncTest`, `MissingBalanceInitTest`| **VERIFIED** |
| **FR-007** | Email OTP & TOTP MFA | `AuthController`, `MfaService`, `EmailOtpService`| `OtpVerificationTest`, `TotpTest`, `MfaFlowTest` | **VERIFIED** |
| **FR-008** | RBAC & Ownership | `AuthMiddleware`, `RoleMiddleware`, `Ownership` | `RbacRoleEnforcementTest`, `OwnershipGuardTest`| **VERIFIED** |
| **FR-009** | WebSocket & Outbox | `RealtimeServer`, `OutboxService`, `realtime.js` | `RealtimePolicyTest`, `OutboxWorkerTest` | **VERIFIED** |
| **FR-010** | Notifications | `NotificationController`, `Notification` | `NotificationCenterTest`, `UnreadCountTest` | **VERIFIED** |
| **FR-011** | Audit Logging | `ActivityLogService`, `activity_logs` table | `AuditTrailLoggingTest`, `TamperEvidenceTest` | **VERIFIED** |
| **FR-012** | Step-Up Auth | `StepUpAuthService`, `Session` | `StepUpAuthTest`, `SensitiveActionLockTest` | **VERIFIED** |
| **FR-013** | Reporting & Export | `ReportController`, `CsvExportService` | `ReportExportTest`, `AnalyticsQueryTest` | **VERIFIED** |
| **FR-014** | DSA Visualizers | `DsaController`, `MergeSort`, `BinarySearch` | `DsaAlgorithmBenchmarkTest`, `VisualizerTest` | **VERIFIED** |
| **FR-015** | OpenAPI / Swagger | `ApiDocsController`, `openapi.yaml` | `SwaggerEndpointTest`, `ApiSchemaValidationTest`| **VERIFIED** |
| **FR-016** | HR Announcements Management | `HrAnnouncementController`, `EmployeeAnnouncementController`, `AnnouncementControllerAdapter`, `MySqlAnnouncementRepository` | `AnnouncementDomainTest`, `AnnouncementLifecycleIntegrationTest`, `AnnouncementRbacAndSecurityTest` | **VERIFIED** |

---

## 8. Constraints & Assumptions

### 8.1 Environmental & Operational Constraints
1. **PHP Runtime:** Must execute on PHP 8.2+ (verified in development on PHP 8.5.12).
2. **Database Engine:** MySQL 8.0+ strictly utilizing the InnoDB storage engine for foreign keys and row-level locking.
3. **Containerized Architecture:** Production runtime orchestrated via Docker Compose (Nginx edge reverse proxy, PHP-Apache application runtime, MySQL database, Ratchet WebSocket daemon, and Prometheus/Grafana monitoring).
4. **No External Framework Dependency:** The core MVC router, database wrapper, middleware dispatcher, and session manager are native, framework-agnostic PHP implementations designed for maximum speed and zero vendor bloat.

### 8.2 Assumptions
1. All users have access to a functional corporate email address capable of receiving standard SMTP multi-factor authentication tokens.
2. The business operating calendar follows a standard Monday through Friday workweek for leave calculation purposes.
3. System clocks between the host machine, MySQL database, and Docker containers are synchronized via NTP to ensure OTP token validities and lock timestamps remain precise.

