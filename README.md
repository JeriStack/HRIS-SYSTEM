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

<ul>
  <li>
    <p align="justify">
      🏛️ <strong><a href="docs/02-technical/system-architecture.md">System Architecture &amp; Components</a></strong> (<code>docs/02-technical/system-architecture.md</code>): Details the verified multi-container Docker topology, 4-tier MVC and Clean/DDD bounded contexts (<code>Leave</code>, <code>Announcement</code>), Anti-Corruption Layer (ACL) controller adapters, and end-to-end HTTP and WebSocket request/data flows.
    </p>
  </li>
  <li>
    <p align="justify">
      📐 <strong><a href="docs/02-technical/uml-diagrams.md">UML Diagrams Catalog</a></strong> (<code>docs/02-technical/uml-diagrams.md</code>): Provides reconciled System Context, UML Use Case, Domain Class, Sequence, Activity, Lifecycle State Machine (Leave, Support, and Announcements), Component, and Container Deployment diagrams.
    </p>
  </li>
  <li>
    <p align="justify">
      🔄 <strong><a href="docs/02-technical/system-workflows.md">System Workflows &amp; Flowcharts</a></strong> (<code>docs/02-technical/system-workflows.md</code>): Traces step-by-step execution flowcharts for Smart OTP Authentication, Dynamic RBAC Governance, HR Workforce Operations, Statutory Leave Adjudication, DDD Announcements Distribution, Transactional Outbox &amp; Queue Worker, and the SHA-256 Tamper-Evident Audit Trail.
    </p>
  </li>
  <li>
    <p align="justify">
      🧮 <strong><a href="docs/02-technical/dsa-documentation.md">DSA Documentation &amp; Flowcharts</a></strong> (<code>docs/02-technical/dsa-documentation.md</code>): Documents the custom PHP Data Structures &amp; Algorithms implementations (Singly Linked List, FIFO Queue, LIFO Stack, Hash Table, Binary Search Tree, Merge/Quick/Insertion/Selection/Bubble Sort, and Binary/Linear Search) with pseudocode, Big-O time/space complexity matrices, and empirical benchmark charts.
    </p>
  </li>
  <li>
    <p align="justify">
      🔒 <strong><a href="docs/02-technical/security/security-architecture.md">Security &amp; Authentication Architecture</a></strong> (<code>docs/02-technical/security/</code>): Covers primary Bcrypt authentication, <a href="docs/02-technical/security/authentication-and-mfa-security.md">Email OTP &amp; RFC 6238 TOTP MFA</a>, HMAC-SHA256 rotating trusted-device cookies, anti-enumeration password recovery, session security fingerprinting, CSRF synchronization, and statutory confidentiality masking (RA 9262 VAWC &amp; RA 9710 SLW).
    </p>
  </li>
  <li>
    <p align="justify">
      🗄️ <strong><a href="docs/02-technical/database/database-schema.md">Database Architecture &amp; ERD</a></strong> (<code>docs/02-technical/database/database-schema.md</code>): Catalogs all 49 MySQL 8.0 InnoDB tables, the editable <a href="docs/02-technical/database/erd.mmd">Mermaid Entity-Relationship Diagram (<code>erd.mmd</code>)</a>, foreign key referential integrity, composite indexes, idempotent SQL migrations, and pessimistic row-level locking (<code>SELECT ... FOR UPDATE</code>).
    </p>
  </li>
  <li>
    <p align="justify">
      🔌 <strong><a href="docs/02-technical/api-reference.md">REST API v1 &amp; OpenAPI 3.0 Reference</a></strong> (<code>docs/02-technical/api-reference.md</code>): Documents the programmatic REST API v1 endpoints, standardized JSON envelopes, role/permission guards, and machine-readable OpenAPI 3.0 specifications (<a href="docs/api/openapi.yaml"><code>openapi.yaml</code></a> and <a href="docs/api/openapi.json"><code>openapi.json</code></a>).
    </p>
  </li>
  <li>
    <p align="justify">
      🧪 <strong><a href="docs/04-quality-assurance/test-plan-and-results.md">Quality Assurance, Testing Strategy &amp; Audit Reports</a></strong> (<code>docs/04-quality-assurance/</code>): Presents verified execution results across <code>764</code> PHPUnit tests (<code>4,078</code> assertions), <code>45</code> Selenium E2E browser tests, PHPStan static analysis, PSR-12 coding standard enforcement, the <a href="docs/04-quality-assurance/testing-strategy.md">Testing Strategy Guide</a>, and the <a href="docs/04-quality-assurance/functional-audit-report.md">Full-System Functional Audit Report</a>.
    </p>
  </li>
  <li>
    <p align="justify">
      ⚡ <strong><a href="docs/04-quality-assurance/performance-test-plan-and-results.md">Performance Test Plan &amp; Empirical Benchmarks</a></strong> (<code>docs/04-quality-assurance/performance-test-plan-and-results.md</code>): Reports reproducible latency percentiles (<code>avg</code>, <code>p50</code>, <code>p95</code>, <code>ops/sec</code>), algorithmic scaling comparisons across dataset sizes, and InnoDB query execution plan analysis.
    </p>
  </li>
  <li>
    <p align="justify">
      🚀 <strong><a href="docs/03-operations/deployment/installation-and-configuration-guide.md">Installation, Deployment, DevOps &amp; Monitoring</a></strong> (<code>docs/03-operations/</code>): Combines the complete setup guide, <a href="docs/03-operations/deployment/deployment-guide.md">Production Deployment Runbook</a>, <a href="docs/03-operations/deployment/production-readiness-checklist.md">18-Point Production Readiness Checklist</a>, <a href="docs/03-operations/devops/cron-scheduler-and-queue-worker.md">Cron Scheduler &amp; Queue Worker Guide</a>, and <a href="docs/03-operations/monitoring/README.md">Prometheus &amp; Grafana Observability Stack</a>.
    </p>
  </li>
  <li>
    <p align="justify">
      🎓 <strong><a href="docs/06-defense/project-defense-guide.md">Academic &amp; Technical Defense Package</a></strong> (<code>docs/06-defense/</code>): Equips presenters with the <a href="docs/06-defense/project-defense-guide.md">Project Defense Guide</a>, <a href="docs/06-defense/anticipated-questions.md">Anticipated Panel Q&amp;A Bank</a>, <a href="docs/06-defense/demonstration-script.md">15-Step Live Demonstration Script</a>, and <a href="docs/06-defense/presentation-outline.md">22-Slide Master Presentation Deck</a>.
    </p>
  </li>
</ul>

---

## ⚡ Core System Capabilities

<p align="justify">
  <strong>NEXORA HR</strong> unifies ten production-hardened engineering capabilities spanning identity security, organizational workforce management, statutory leave accounting, multi-tier support workflows, real-time event streaming, and algorithmic computation:
</p>

<ul>
  <li>
    <p align="justify">
      🛡️ <strong>Dynamic 3-Role RBAC &amp; Least-Privilege Governance (<code>ADR-012</code> / <code>ADR-013</code>):</strong> Enforces fail-closed, database-driven access control across three mutually exclusive roles—<strong>Administrator</strong> (<code>/admin/dashboard</code>, <code>/admin/users*</code>, <code>/admin/roles</code>, <code>/admin/support*</code>), <strong>HR Officer</strong> (<code>/hr/dashboard</code>, <code>/hr/employees*</code>, <code>/hr/announcements*</code>, <code>/hr/support*</code>, <code>/hr/help*</code>), and <strong>Employee</strong> (<code>/employee/dashboard</code>, <code>/employee/profile</code>, <code>/employee/announcements*</code>, <code>/support*</code>)—backed by 44 granular permissions in <code>Core\PermissionRegistry</code> and atomic role-permission auditing (<code>permission_audit_logs</code>).
    </p>
  </li>
  <li>
    <p align="justify">
      🔑 <strong>Controlled CLI Admin Bootstrapping &amp; Lockout Protection:</strong> Prohibits public Administrator registration; initial Admin accounts are provisioned exclusively via the hardened CLI utility (<code>bin/create_admin_user.php</code>) with Bcrypt cost-12 hashing, password complexity enforcement, and server-side guards that prevent self-demotion, self-deactivation, or deactivating the last active Administrator.
    </p>
  </li>
  <li>
    <p align="justify">
      🔐 <strong>Smart Email OTP, RFC 6238 TOTP MFA &amp; Trusted Devices:</strong> Combines 6-digit cryptographic Email OTP challenges and RFC 6238 TOTP authenticator support with single-use rotating HMAC-SHA256 trusted-browser cookies (<code>trusted_devices</code>), risk-based step-up verification (<code>AuthRiskService</code>), timing-equalized anti-enumeration password recovery, and self-service device revocation at <code>/account/security</code>.
    </p>
  </li>
  <li>
    <p align="justify">
      🔄 <strong>Continuous Live Session &amp; Account-State Verification:</strong> Executes <code>Session::verifyAuthenticatedAccountState()</code> on every authenticated HTTP request to verify that the user and linked employee record remain <code>Active</code>, validate cryptographic session fingerprints, synchronize department/position metadata in real time, and immediately terminate revoked or deactivated sessions.
    </p>
  </li>
  <li>
    <p align="justify">
      📅 <strong>Philippine Statutory &amp; Company Leave Engine:</strong> Supports Vacation Leave, Sick Leave, Emergency Leave, Service Incentive Leave (SIL), Expanded Maternity Leave (RA 11210), Paternity Leave (RA 8187), Solo Parent Leave (RA 8972), VAWC Leave (RA 9262), and Special Leave for Women (RA 9710) with versioned policy rules (<code>leave_policy_versions</code>), statutory qualification verification, confidential reason/document redaction, and automatic Monday–Friday business-day calculation.
    </p>
  </li>
  <li>
    <p align="justify">
      🔒 <strong>Pessimistic Concurrency Locking (<code>SELECT ... FOR UPDATE</code>):</strong> Processes leave adjudications through a FIFO approval queue inside atomic InnoDB transactions that acquire row-level locks on <code>leave_requests</code> and <code>leave_balances</code>, guaranteeing zero race conditions, double-deductions, or negative balance overdrafts under concurrent HR reviews.
    </p>
  </li>
  <li>
    <p align="justify">
      🎫 <strong>Multi-Tier Help, Support &amp; Confidential Complaints:</strong> Provides three isolated support channels—tokenized pre-login authentication assistance (<code>AUTH-YYYY-NNNNN</code>), Employee-to-HR Help &amp; Support and Confidential Workplace Complaints (<code>SUP-YYYY-NNNNN</code>) with automatic conflict-of-interest (COI) handler exclusions, and HR-to-Admin technical escalation ticketing (<code>HR-SUP-YYYY-XXXXXXXX</code>) with strict separation of private Admin internal notes.
    </p>
  </li>
  <li>
    <p align="justify">
      📢 <strong>DDD Company Announcements Management:</strong> Implements a dedicated Domain-Driven Design bounded context (<code>App\Domain\Announcement</code>) managing the complete announcement lifecycle (<code>Draft</code>, <code>Scheduled</code>, <code>Published</code>, <code>Expired</code>, <code>Archived</code>) with company-wide or department-targeted audiences, pinned notices, automated CRON publication and expiration (<code>process-announcements</code>), outside-web-root attachments, and idempotent employee read/acknowledgment receipts (<code>announcement_reads</code>).
    </p>
  </li>
  <li>
    <p align="justify">
      ⚡ <strong>Real-Time WebSockets, Outbox, Cron Scheduler &amp; Queue Worker:</strong> Delivers sub-100ms browser notifications over a Ratchet WebSocket server (<code>:8090</code>) authorized via single-use handshake tickets (<code>POST /realtime/ticket</code>), backed by a Transactional Outbox (<code>outbox_events</code>), a dedicated Docker Cron Scheduler (<code>hris_scheduler</code>), and a concurrency-safe <code>FOR UPDATE SKIP LOCKED</code> MySQL Queue Worker (<code>hris_worker</code>).
    </p>
  </li>
  <li>
    <p align="justify">
      🧮 <strong>Custom DSA Laboratory, SHA-256 Audit Chain &amp; OpenAPI Portal:</strong> Features native PHP implementations of Singly Linked Lists, FIFO Queues, LIFO Stacks, Hash Tables, Binary Search Trees, and Merge, Quick, Insertion, Selection, Bubble, Binary, and Linear Search algorithms alongside a SHA-256 hash-chained tamper-evident audit trail (<code>Core\AuditService</code>), Prometheus metrics (<code>/metrics</code>), and an interactive Swagger UI portal (<code>/docs</code>).
    </p>
  </li>
</ul>

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
