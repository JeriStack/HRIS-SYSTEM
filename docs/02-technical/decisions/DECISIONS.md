# NEXORA HR — Architectural Decision Records (ADRs)

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Architectural Decision Catalog  
**Date:** November 2026  
**Document Owner:** Repository Maintainer & System Architects  

---

## 1. Overview & ADR Methodology

This document maintains the official Architectural Decision Records (ADRs) governing the evolution of **NEXORA HR**. Each record captures the business context, technical trade-offs, and final decisions implemented across the platform.

### Decision Status Categories
* **`ACCEPTED`**: Active, authoritative standard implemented and enforced in the repository.
* **`SUPERSEDED`**: Replaced or updated by a subsequent architectural decision record.
* **`DEPRECATED`**: Historical proposal no longer supported in production.

---

## 2. Architectural Decision Registry

| ADR ID | Title | Status | Impact Area |
|---|---|---|---|
| **ADR-001** | Custom Framework-Agnostic PHP 8.5 MVC Architecture | `ACCEPTED` | Core Framework |
| **ADR-002** | Strict 2-Role Access Control Model (HR & Employee) | `ACCEPTED` (Supersedes DEC-012) | Security / RBAC |
| **ADR-003** | MySQL 8.0 InnoDB Engine with Referential Integrity | `ACCEPTED` | Database |
| **ADR-004** | Multi-Container Docker Compose Orchestration | `ACCEPTED` | Infrastructure |
| **ADR-005** | Mandatory Dual-Factor Authentication (Email OTP & TOTP) | `ACCEPTED` | Authentication |
| **ADR-006** | Ratchet WebSocket Daemon & Transactional Outbox Pattern | `ACCEPTED` | Real-time Events |
| **ADR-007** | Pessimistic Row-Level Locking for Leave Concurrency | `ACCEPTED` | Leave Engine |
| **ADR-008** | Business Day (Mon-Fri) Leave Calculation | `ACCEPTED` | Business Rules |
| **ADR-009** | On-Demand Missing Leave Balance Initialization Policy | `ACCEPTED` | Leave Balances |
| **ADR-010** | Continuous Session State Verification & Live Profile Sync | `ACCEPTED` | Session Security |
| **ADR-011** | PSR-12 Baseline Code Quality Gate | `ACCEPTED` | Quality / CI |

---

## 3. Detailed Decision Records

### ADR-001: Custom Framework-Agnostic PHP 8.5 MVC Architecture
* **Status:** `ACCEPTED`
* **Context:** Modern web applications frequently carry excessive overhead from third-party frameworks (Laravel, Symfony), creating complex dependency trees, slower cold starts, and framework lock-in.
* **Decision:** Build a lean, framework-agnostic 4-tier MVC engine (`Router`, `Middleware`, `Controller`, `Model`, `View`) in pure PHP 8.5 with zero vendor bloat.
* **Consequences:** Near-instant request boot times (<10ms bootstrap), complete architectural transparency, zero external framework CVE vulnerabilities, and straightforward maintenance.

---

### ADR-002: Strict 2-Role Access Control Model
* **Status:** `ACCEPTED` (Supersedes historical `DEC-012` 3-role proposal)
* **Context:** Earlier prototypes proposed three separate roles (`admin`, `hr`, `employee`). In production HRIS environments, separating Admin and HR introduces operational ambiguity, permission conflicts, and privilege escalation risks.
* **Decision:** Enforce strictly **two application roles**: **`hr`** and **`employee`**.
  * The `hr` role consolidates workforce administration, organization configuration, and leave processing.
  * The `employee` role provides isolated self-service.
  * All legacy `/admin/*` routes are permanent HTTP 301 redirects to `/hr/*`.
* **Consequences:** Eliminates administrative privilege sprawl, simplifies the authorization matrix, and prevents security confusion.

---

### ADR-003: MySQL 8.0 InnoDB Engine with Referential Integrity
* **Status:** `ACCEPTED`
* **Context:** Managing leave balances and audit histories requires relational integrity and atomic rollbacks.
* **Decision:** Mandate MySQL 8.0 with `ENGINE=InnoDB` across all 13 tables. Enforce foreign keys with `ON DELETE RESTRICT` on departments, positions, and employees, preventing accidental cascades that could orphan leave records.
* **Consequences:** Guarantees database-level referential integrity and prevents accidental data deletion.

---

### ADR-004: Multi-Container Docker Compose Orchestration
* **Status:** `ACCEPTED`
* **Context:** Local developer environments often diverge in PHP extensions, MySQL configurations, and web server modules.
* **Decision:** Containerize the application stack into an orchestrated Docker Compose environment featuring Nginx (edge reverse proxy on `:8080`), Apache/PHP 8.5, MySQL 8.0, Ratchet WebSocket daemon, and Prometheus/Grafana monitoring.
* **Consequences:** Guarantees 100% environment parity across Windows, macOS, Linux, and production cloud servers.

---

### ADR-005: Mandatory Dual-Factor Authentication (Email OTP & TOTP)
* **Status:** `ACCEPTED`
* **Context:** Single-factor password authentication exposes sensitive workforce records to credential stuffing and phishing attacks.
* **Decision:** Enforce mandatory 2FA on every sign-in via 6-digit cryptographic Email OTP (10m TTL), supported by optional RFC 6238 TOTP authenticator backup.
* **Consequences:** Elevates security posture to enterprise defense-in-depth while preventing brute-force attacks via rate limits and challenge attempt caps.

---

### ADR-006: Ratchet WebSocket Daemon & Transactional Outbox Pattern
* **Status:** `ACCEPTED`
* **Context:** Real-time dashboard updates should not depend on synchronous third-party cloud services or risk message loss during network transport failures.
* **Decision:** Deploy an internal Ratchet WebSocket daemon (:8090) coupled with a database-backed **Transactional Outbox** (`outbox_events`). Domain events are written to the outbox inside the primary business transaction and polled by an asynchronous worker.
* **Consequences:** Delivers sub-100ms real-time event distribution with zero message loss and complete decoupling from transport failures.

---

### ADR-007: Pessimistic Row-Level Locking for Leave Concurrency
* **Status:** `ACCEPTED`
* **Context:** Simultaneous approvals of leave requests or concurrent employee submissions risk double-allocation, negative balances, and race conditions.
* **Decision:** Execute all leave status mutations and balance deductions inside atomic database transactions using pessimistic row locks (`SELECT ... FOR UPDATE`) on both `leave_requests` and `leave_balances`.
* **Consequences:** Completely eliminates race conditions and overdraft risks under high-concurrency multi-officer review workflows.

---

### ADR-008: Business Day (Mon-Fri) Leave Calculation
* **Status:** `ACCEPTED`
* **Context:** Employees should not have leave balances deducted for non-working calendar days.
* **Decision:** Calculate requested leave durations by iterating between `start_date` and `end_date`, counting only Monday through Friday (business days). Saturdays and Sundays are excluded from balance deduction.
* **Consequences:** Delivers fair, standardized leave accounting without manual calculation errors.

---

### ADR-009: On-Demand Missing Leave Balance Initialization Policy
* **Status:** `ACCEPTED`
* **Context:** When new employees are registered or a new calendar year commences, employees may submit leave requests before administrative quota records are initialized.
* **Decision:** When querying balances or processing leave requests, if an employee has no balance record for the active calendar year, the system safely initializes the default annual allocation on-demand rather than throwing an exception or returning zero.
* **Consequences:** Eliminates application crashes and administrative blockers for newly hired staff.

---

### ADR-010: Continuous Session State Verification & Live Profile Sync
* **Status:** `ACCEPTED`
* **Context:** Traditional web sessions remain valid until explicit logout, allowing deactivated employees to continue accessing the system and caching stale profile details.
* **Decision:** Implement `Session::verifyAuthenticatedAccountState()` on every authenticated HTTP request. Re-evaluate user status, linked employee status, role, and password hash in MySQL. Terminate deactivated accounts immediately and synchronize updated employee profile details live.
* **Consequences:** Guarantees real-time account revocation and seamless navbar/dashboard synchronization.

---

### ADR-011: PSR-12 Baseline Code Quality Gate
* **Status:** `ACCEPTED`
* **Context:** Large legacy codebases cannot immediately resolve all historical styling discrepancies without creating massive git merge conflicts.
* **Decision:** Enforce a baseline sniffer (`bin/check-phpcs-baseline.php`) locking legacy violations to 259, while mandating **zero new violations** on all new code changes and pull requests.
* **Consequences:** Incrementally improves codebase quality without disrupting active feature development.

