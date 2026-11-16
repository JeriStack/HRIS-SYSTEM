<div align="center">

<img src="public/assets/img/nexora-logo-icon.png" alt="NEXORA HR Logo" width="130" />

# NEXORA HR — People. Processes. Progress.

**Enterprise Human Resource Information System (HRIS), Statutory Leave Engine &amp; DSA Workbench**

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20--%208.5-2563EB.svg?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%208.0%20InnoDB%20(49%20Tables)-F59E0B.svg?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-4--Tier%20MVC%20%2B%20DDD%20%7C%203--Role%20RBAC-10B981.svg?style=flat-square)](docs/01-core/ARCHITECTURE.md)
[![Backend Tests](https://img.shields.io/badge/PHPUnit-764%20Tests%20(0%20Failures)-16A34A.svg?style=flat-square)](docs/04-quality-assurance/testing-strategy.md)
[![Code Style](https://img.shields.io/badge/PSR--12%20%26%20PHPStan-0%20Errors-22C55E.svg?style=flat-square)](docs/04-quality-assurance/testing-strategy.md)
[![UI Tests](https://img.shields.io/badge/Selenium%20E2E-45%20Tests%20Passed%20(Chrome%20%7C%20Brave)-16A34A.svg?style=flat-square)](e2e-tests/README.md)

[**Documentation Hub**](docs/README.md) &middot;
[**System Architecture**](docs/01-core/ARCHITECTURE.md) &middot;
[**User Manual**](docs/01-core/USER-MANUAL.md) &middot;
[**Quick-Start**](#-quick-start-guide-docker-compose) &middot;
[**Automated Tests**](#-running-automated-tests) &middot;
[**Defense Guide**](docs/06-defense/project-defense-guide.md)

</div>

<blockquote>
  <p align="justify">
    <strong>NEXORA HR</strong> (<em>People. Processes. Progress.</em>) is a production-grade Enterprise Human Resource Information System (HRIS), Statutory Leave Management Platform, and Data Structures &amp; Algorithms (DSA) Workbench engineered with native PHP 8.5, MySQL 8.0 (InnoDB), and Domain-Driven Design (DDD). Designed to unify organizational workforce operations with rigorous computer science fundamentals, the platform enforces a fail-closed, database-driven three-role access model (<code>admin</code>, <code>hr</code>, and <code>employee</code>) backed by 44 granular permissions, continuous session-state verification, and a SHA-256 hash-chained tamper-evident audit trail.
  </p>
  <p align="justify">
    Across its role-scoped workspaces, NEXORA HR delivers end-to-end employee lifecycle management, a Philippine statutory and company leave ledger protected by pessimistic row-level locking (<code>SELECT ... FOR UPDATE</code>), Smart Email OTP and RFC 6238 TOTP Multi-Factor Authentication with cryptographic trusted-device rotation, anti-enumeration password recovery, tokenized pre-login assistance (<code>AUTH-YYYY-NNNNN</code>), Employee-to-HR Help &amp; Support with conflict-of-interest-isolated confidential workplace complaints (<code>SUP-YYYY-NNNNN</code>), HR-to-Admin technical escalation ticketing (<code>HR-SUP-YYYY-XXXXXXXX</code>), and scheduled/targeted Company Announcements with employee read and acknowledgment receipts (<code>ANN-YYYY-XXXXXXXX</code>). Real-time notifications are broadcast in under 100ms via a Ratchet WebSocket server (:8090), Transactional Outbox, and MySQL-backed Queue Worker, orchestrated inside a multi-container Docker Compose stack with Nginx, Prometheus, Grafana, and OpenAPI 3.0 documentation.
  </p>
</blockquote>

---

## 📚 Authoritative Documentation Architecture

<p align="justify">
  <strong>NEXORA HR</strong> maintains a single-source-of-truth documentation suite organized into six numbered pillars inside <a href="docs/README.md"><code>docs/</code></a> (<code>01-core</code> through <code>06-defense</code>, plus machine-readable OpenAPI specifications and verified visual evidence):
</p>

<table>
  <thead>
    <tr>
      <th align="center">Document</th>
      <th align="center">Purpose &amp; Description</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td align="center"><strong><a href="docs/01-core/PRD.md">PRD.md</a></strong></td>
      <td align="justify">
        <strong>Product Requirements Document:</strong> Defines the executive product vision, three-role operational scope (<code>Admin</code>, <code>HR</code>, <code>Employee</code>), functional requirements (<code>FR-001</code>–<code>FR-016</code>), Philippine statutory and company leave rules, non-functional security/performance SLAs, and the verified Requirements Traceability Matrix (RTM).
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/ARCHITECTURE.md">ARCHITECTURE.md</a></strong></td>
      <td align="justify">
        <strong>System Architecture Specification:</strong> Documents the custom 4-tier MVC and Domain-Driven Design (DDD) bounded contexts, 49-table MySQL 8.0 InnoDB schema, pessimistic row-level concurrency locking (<code>SELECT ... FOR UPDATE</code>), Transactional Outbox WebSocket pipeline (:8090), Dedicated Cron Scheduler container, and MySQL Queue Worker.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/USER-MANUAL.md">USER-MANUAL.md</a></strong></td>
      <td align="justify">
        <strong>Three-Role User Manual:</strong> Provides step-by-step operational guides for Administrators (<code>/admin/*</code>), HR Officers (<code>/hr/*</code>), and Employees (<code>/employee/*</code>) covering account provisioning, RBAC governance, leave filing and FIFO adjudication, multi-tier support ticketing, company announcements, and validation messages.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/RULES.md">RULES.md</a></strong></td>
      <td align="justify">
        <strong>Engineering, Security &amp; AI Agent Rules:</strong> Enforces mandatory least-privilege boundaries under <code>ADR-012</code> and <code>ADR-013</code>, server-side ownership guards, CSRF and session-fingerprint protections, PSR-12 coding standards, database migration parity rules, and non-destructive operational guardrails.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/DESIGN.md">DESIGN.md</a></strong></td>
      <td align="justify">
        <strong>UI/UX Design System:</strong> Establishes the Cobalt Blue and Deep Slate enterprise visual language, typography scale, 8px spatial grid, responsive App Shell, native <code>&lt;dialog&gt;</code> and semantic table patterns, Light/Dark Mode token parity, and WCAG 2.2 AA accessibility compliance.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/TASK.md">TASK.md</a></strong></td>
      <td align="justify">
        <strong>Development Roadmap &amp; Milestone Tracker:</strong> Tracks engineering execution across Phases 1–16 and scope addenda (<code>v3.1</code>–<code>v3.3</code>), live quality gate metrics (<code>764</code> PHPUnit tests, <code>45</code> Selenium E2E tests, <code>0</code> PHPStan/PSR-12 regressions), and production release readiness.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/MEMORY.md">MEMORY.md</a></strong></td>
      <td align="justify">
        <strong>AI Agent Context Primer:</strong> Supplies a high-density architectural reference map, core domain invariants, canonical file locations across MVC and DDD layers, database isolation rules (<code>hris_db</code> vs. <code>hris_test</code>), and essential CLI verification commands.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/01-core/modules/">01-core/modules/</a></strong></td>
      <td align="justify">
        <strong>Feature &amp; Module Specifications:</strong> Contains dedicated specifications for <a href="docs/01-core/modules/announcements-management.md">Announcements Management</a>, <a href="docs/01-core/modules/employee-help-and-support.md">Employee Support &amp; Confidential Complaints</a>, <a href="docs/01-core/modules/hr-admin-help-and-support.md">HR-to-Admin Support</a>, <a href="docs/01-core/modules/smart-otp-trusted-devices.md">Smart OTP &amp; Trusted Devices</a>, and <a href="docs/01-core/modules/public-help-and-password-recovery.md">Public Help &amp; Password Recovery</a>.
      </td>
    </tr>
    <tr>
      <td align="center"><strong><a href="docs/README.md">docs/README.md</a></strong></td>
      <td align="justify">
        <strong>Unified Technical Documentation Hub:</strong> Serves as the master directory indexing all specialized architecture, database/ERD, UML, workflow flowcharts, custom DSA, security, REST API/OpenAPI, DevOps/Terraform, Prometheus/Grafana monitoring, QA benchmark, RBAC reference, and academic defense packages.
      </td>
    </tr>
  </tbody>
</table>

### Priority Technical, QA & Defense Guides
* 🏛️ **[System Architecture & Components (docs/02-technical/system-architecture.md)](docs/02-technical/system-architecture.md):** Verified container topology, Clean Architecture layers, and request/data flow.
* 📐 **[UML Diagrams Catalog (docs/02-technical/uml-diagrams.md)](docs/02-technical/uml-diagrams.md):** Context, Use Case, Class, Sequence, Activity, State Machine, Component, and Deployment diagrams.
* 🔄 **[System Workflows & Flowcharts (docs/02-technical/system-workflows.md)](docs/02-technical/system-workflows.md):** Reconciled flowcharts for Authentication, Admin, HR, Employee, Leave, Announcements, Outbox/Queue, and Audit Trail.
* 🧮 **[DSA Documentation & Flowcharts (docs/02-technical/dsa-documentation.md)](docs/02-technical/dsa-documentation.md):** Custom Data Structures and Algorithms specifications, pseudocode, complexity matrices, and flowcharts.
* 🔒 **[Security Architecture (docs/02-technical/security/security-architecture.md)](docs/02-technical/security/security-architecture.md):** Authentication lifecycle, Email OTP / TOTP MFA, session security fingerprinting, CSRF, and PII masking.
* 🗄️ **[Database Architecture & ERD (docs/02-technical/database/database-schema.md)](docs/02-technical/database/database-schema.md):** 49-table InnoDB schema catalog, [Mermaid ERD (`erd.mmd`)](docs/02-technical/database/erd.mmd), foreign key constraints, and pessimistic row locking (`SELECT ... FOR UPDATE`).
* 🧪 **[QA Test Plan & Verified Results (docs/04-quality-assurance/test-plan-and-results.md)](docs/04-quality-assurance/test-plan-and-results.md):** PHPUnit (764 tests, 4,078 assertions), [Testing Strategy](docs/04-quality-assurance/testing-strategy.md), and [Full-System Functional Audit](docs/04-quality-assurance/functional-audit-report.md).
* ⚡ **[Performance Test Plan & Results (docs/04-quality-assurance/performance-test-plan-and-results.md)](docs/04-quality-assurance/performance-test-plan-and-results.md):** Empirical latency percentiles (`avg`, `p50`, `p95`, `ops/sec`) and index analysis.
* 🚀 **[Installation, Deployment & Readiness (docs/03-operations/deployment/installation-and-configuration-guide.md)](docs/03-operations/deployment/installation-and-configuration-guide.md):** Complete setup guide, [Deployment Runbook](docs/03-operations/deployment/deployment-guide.md), and [18-Point Production Readiness Checklist](docs/03-operations/deployment/production-readiness-checklist.md).
* 🎓 **[Defense & Presentation Package (docs/06-defense/project-defense-guide.md)](docs/06-defense/project-defense-guide.md):** [Defense Guide](docs/06-defense/project-defense-guide.md), [Anticipated Q&A Bank](docs/06-defense/anticipated-questions.md), [15-Step Live Demo Script](docs/06-defense/demonstration-script.md), and [22-Slide Presentation Outline](docs/06-defense/presentation-outline.md).

---

## ⚡ Core System Capabilities

* **Dynamic 3-Role RBAC & Least-Privilege Governance (`ADR-012` / `ADR-013`):** Strict server-side separation across Administrators (`/admin/dashboard`, `/admin/users*`, `/admin/roles`, `/admin/support*`), HR Officers (`/hr/dashboard`, `/hr/employees*`, `/hr/announcements*`, `/hr/support*`, `/hr/help*`), and Employees (`/employee/dashboard`, `/employee/profile`, `/employee/announcements*`, `/support*`) across 44 dynamic permissions.
* **Smart OTP, TOTP MFA & Trusted Device Security:** Mandatory 6-digit Email OTP and RFC 6238 TOTP verification paired with HMAC-SHA256 single-use rotating trusted-browser cookies, risk-based step-up challenges, anti-enumeration password recovery, and self-service device revocation (`/account/security`).
* **Philippine Statutory & Company Leave Engine:** Supports Vacation, Sick, Service Incentive Leave (SIL), Maternity (RA 11210), Paternity (RA 8187), Solo Parent (RA 8972), VAWC (RA 9262), and Special Leave for Women (RA 9710) with confidentiality masking, qualification verification, and Monday–Friday business-day calculation.
* **Pessimistic Concurrency Locking (`SELECT ... FOR UPDATE`):** FIFO leave approval queue executing inside atomic InnoDB transactions with row-level locks on `leave_requests` and `leave_balances` to prevent race conditions and balance overdrafts.
* **Multi-Tier Help, Support & Confidential Complaints:** Dedicated Employee-to-HR Support & Confidential Complaint handling (`SUP-YYYY-NNNNN`) with COI exclusions, pre-login authentication assistance (`AUTH-YYYY-NNNNN`), and isolated HR-to-Admin technical support (`HR-SUP-YYYY-XXXXXXXX`) with private Admin internal notes.
* **DDD Company Announcements Management:** Full announcement lifecycle (`Draft`, `Scheduled`, `Published`, `Expired`, `Archived`) with department/company-wide audience targeting, automated CRON publication/expiration (`process-announcements`), attachment validation, and idempotent employee read/acknowledgment receipts.
* **Real-Time WebSockets, Outbox & Queue Worker:** Sub-100ms push notifications via a Ratchet WebSocket server (`:8090`), Transactional Outbox (`outbox_events`), and `FOR UPDATE SKIP LOCKED` MySQL Queue Worker (`bin/queue-worker.php`).
* **Custom Data Structures & Algorithms (DSA) Laboratory:** Native PHP implementations of Singly Linked Lists, FIFO Queues, LIFO Stacks, Hash Tables, Binary Search Trees, and Merge/Quick/Insertion/Selection/Bubble Sort & Binary/Linear Search with empirical benchmarking.

---

## 🚀 Quick-Start Guide (Docker Compose)

The fastest and recommended way to start NEXORA HR is using Docker Compose:

```bash
# 1. Clone the repository
git clone https://github.com/JeriStack/HRIS-SYSTEM.git
cd HRIS-SYSTEM

# 2. Start container stack (Nginx, Apache/PHP 8.5, MySQL 8.0, WebSocket, Prometheus, Grafana)
docker compose up -d --build

# 3. Execute database migrations
docker compose exec app php bin/migrate.php

# 4. (Optional) Run initial database seeds
docker compose exec app php database/seeds/DatabaseSeeder.php
```

### Accessing Local Services

| Interface | URL | Access / Credentials |
|---|---|---|
| **NEXORA HR Application** | [http://localhost:8080](http://localhost:8080) | Portals: `/hr/dashboard` or `/employee/dashboard` |
| **phpMyAdmin** | [http://localhost:8081](http://localhost:8081) | Host: `db`, User: `root`, Pass: `rootpassword` |
| **Swagger UI Documentation** | [http://localhost:8080/docs](http://localhost:8080/docs) | Interactive OpenAPI 3.0 Explorer |
| **Prometheus Telemetry** | [http://localhost:9090](http://localhost:9090) | Metric scrapers (`nginx`, `mysqld`) |
| **Grafana Dashboards** | [http://localhost:3000](http://localhost:3000) | Default: `admin` / `admin` |
| **Mailpit (Local Email/OTP)** | [http://localhost:8025](http://localhost:8025) | Local SMTP mailbox for 6-digit OTP codes |

### Pre-Seeded Default Accounts

| Account Email | Role | Password | Target Portal |
|---|---|---|---|
| `marcus.tan@company.local` | Human Resources (`hr`) | `Admin@123` | `/hr/dashboard` |
| `sofia.reyes@company.local` | Human Resources (`hr`) | `Admin@123` | `/hr/dashboard` |
| `david.cruz@company.local` | Employee (`employee`) | `Employee@123` | `/employee/dashboard` |
| `maria.santos@company.local` | Employee (`employee`) | `Employee@123` | `/employee/dashboard` |

*Note: Retrieve your 6-digit OTP token from Mailpit at [http://localhost:8025](http://localhost:8025) during sign-in.*

---

## 🧪 Running Automated Tests

### 1. PHPUnit Automated Test Suite (764 Tests)
Run the entire backend test suite:
```bash
./vendor/bin/phpunit
```
*Current Live Result:* **764 tests, 4,078 assertions, 0 errors, 0 failures (100% passing)**.

### 2. Code Style & Static Analysis Gates
```bash
php bin/check-phpcs-baseline.php
php vendor/bin/phpstan analyse --memory-limit=512M
```
*Requirement:* Must introduce **0 new PSR-12 violations** against the project baseline and **0 PHPStan errors**.

### 3. Selenium E2E Browser Automation (45 Tests)
```powershell
cd e2e-tests
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
pytest tests/ -v
```

---

## 📄 License & Attribution

<p align="justify">
  <strong>NEXORA HR</strong> is engineered and maintained as an enterprise Human Resource Information System (HRIS), statutory leave management platform, and Data Structures &amp; Algorithms (DSA) engineering workbench. Distributed under the terms of the <a href="LICENSE">MIT License</a>.
</p>
