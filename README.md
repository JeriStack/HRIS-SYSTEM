# NEXORA HR — People. Processes. Progress.

[![PHP Version](https://img.shields.io/badge/PHP-8.2%20--%208.5-blue.svg)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MySQL%208.0%20InnoDB-orange.svg)](https://www.mysql.com/)
[![Architecture](https://img.shields.io/badge/Architecture-Custom%204--Tier%20MVC%20%7C%203--Role%20RBAC-green.svg)]()
[![Backend Tests](https://img.shields.io/badge/PHPUnit-669%20Tests%20(0%20Failures)-brightgreen.svg)]()
[![Code Style](https://img.shields.io/badge/PSR--12-Baseline%20Enforced%20(0%20new)-success.svg)]()
[![UI Tests](https://img.shields.io/badge/Selenium%20E2E-Passed%20(Brave%20%7C%20Chromium)-success.svg)]()

> **NEXORA HR** (*People. Processes. Progress.*) is an enterprise-grade Human Resource Information System (HRIS) and Leave Management Platform built on native PHP 8.5 and MySQL 8.0 (InnoDB). The system features strict 3-role RBAC (`admin`, `hr`, `employee`), controlled CLI Administrator bootstrapping, mandatory dual-factor authentication (Email OTP & TOTP), working-day leave accounting with pessimistic row locking, real-time WebSocket notifications via a Transactional Outbox, and automated testing suites.

---

## 📚 Authoritative Documentation Architecture

NEXORA HR maintains authoritative documentation centralized within [`docs/`](docs/README.md):

| Document | Purpose & Description |
|---|---|
| **[PRD.md](docs/01-core/PRD.md)** | **Product Requirements Document:** Business vision, functional requirements (FR-001–015), leave business rules, NFRs, and RTM table. |
| **[ARCHITECTURE.md](docs/01-core/ARCHITECTURE.md)** | **System Architecture:** 4-tier MVC engine, database architecture, pessimistic locking, Transactional Outbox, and Mermaid diagrams. |
| **[user-manual.md](docs/01-core/user-manual.md)** | **Three-Role User Manual:** Step-by-step operations for Admin, HR, and Employee roles with permissions, expected outcomes, and screenshot references. |
| **[RULES.md](docs/01-core/RULES.md)** | **Engineering & AI Agent Rules:** Mandatory 3-role constraints under ADR-012, PSR-12 standards, operational safety, and prohibited actions. |
| **[DESIGN.md](docs/01-core/DESIGN.md)** | **UI/UX Design System:** Color tokens, typography, spatial grid, responsive App Shell, and WCAG 2.1 AA accessibility guidelines. |
| **[TASK.md](docs/01-core/TASK.md)** | **Roadmap & Progress Tracking:** Phase breakdown (Phases 1–16), verified test metrics (669 passing tests), and active tasks. |
| **[MEMORY.md](docs/01-core/MEMORY.md)** | **AI Context Primer:** High-density context primer, key file paths, core constraints, and essential CLI commands. |
| **[docs/README.md](docs/README.md)** | **Technical Documentation Hub:** Unified directory connecting specialized security, database, UML, DSA, testing, API, deployment, and defense guides. |

### Priority Technical, QA & Defense Guides
* 🏛️ **[System Architecture & Components (docs/02-technical/architecture/system-architecture.md)](docs/02-technical/architecture/system-architecture.md):** Verified container topology, Clean Architecture layers, and request/data flow.
* 📐 **[UML Diagrams Catalog (docs/02-technical/uml/uml-diagrams.md)](docs/02-technical/uml/uml-diagrams.md):** Context, Use Case, Class, Sequence, Activity, Component, and Deployment diagrams.
* 🔄 **[System Workflows & Flowcharts (docs/02-technical/workflows/system-workflows.md)](docs/02-technical/workflows/system-workflows.md):** Reconciled flowcharts for Authentication, Admin, HR, Employee, Leave, Outbox/Queue, and Audit Trail.
* 🧮 **[DSA Documentation & Flowcharts (docs/02-technical/dsa/dsa-documentation.md)](docs/02-technical/dsa/dsa-documentation.md):** Custom Data Structures and Algorithms specifications, pseudocode, complexity matrices, and flowcharts.
* 🔒 **[Security Architecture (docs/02-technical/security/SECURITY.md)](docs/02-technical/security/SECURITY.md):** Authentication lifecycle, Email OTP / TOTP MFA, session security fingerprinting, CSRF, and PII masking.
* 🗄️ **[Database Architecture & ERD (docs/02-technical/database/DATABASE.md)](docs/02-technical/database/DATABASE.md):** 18-table InnoDB schema catalog, [Mermaid ERD (`erd.mmd`)](docs/02-technical/database/erd.mmd), foreign key constraints, and pessimistic row locking (`SELECT ... FOR UPDATE`).
* 🧪 **[QA Test Plan & Verified Results (docs/04-quality-assurance/test-plan-and-results.md)](docs/04-quality-assurance/test-plan-and-results.md):** PHPUnit (669 tests, 2,528 assertions), PHPStan, PSR-12 baseline, and full-system audit regression suite.
* ⚡ **[Performance Test Plan & Results (docs/04-quality-assurance/performance-test-plan-and-results.md)](docs/04-quality-assurance/performance-test-plan-and-results.md):** Empirical latency percentiles (`avg`, `p50`, `p95`, `ops/sec`) and index analysis.
* 🚀 **[Installation, Deployment & Readiness (docs/03-operations/installation-and-configuration-guide.md)](docs/03-operations/installation-and-configuration-guide.md):** Complete setup guide, [Deployment Runbook](docs/03-operations/deployment/DEPLOYMENT.md), and [18-Point Production Readiness Checklist](docs/03-operations/production-readiness-checklist.md).
* 🎓 **[Defense & Presentation Package (docs/05-defense/project-defense-guide.md)](docs/05-defense/project-defense-guide.md):** [Defense Guide](docs/05-defense/project-defense-guide.md), [Anticipated Q&A Bank](docs/05-defense/anticipated-questions.md), [15-Step Live Demo Script](docs/05-defense/demonstration-script.md), and [22-Slide Presentation Outline](docs/05-defense/presentation-outline.md).

---

## ⚡ Core System Capabilities

* **Authoritative 3-Role RBAC (ADR-012):** Segregated interfaces for Administrators (`/admin/dashboard`, `/admin/users*`), Human Resources (`/hr/dashboard`), and Employees (`/employee/dashboard`).
* **Controlled Admin Provisioning:** Initial Administrator creation is protected via CLI utility (`bin/create_admin_user.php`) with password complexity, audit logging, and lockout protection.
* **Multi-Factor Authentication (MFA):** Mandatory 6-digit numeric Email OTP verification with 10-minute validity, supported by RFC 6238 TOTP backup.
* **Continuous Session Verification:** `Session::verifyAuthenticatedAccountState()` validates account status and synchronizes employee names/positions live on every HTTP request.
* **Business-Day Leave Engine:** Automatic working-day calculation (Monday through Friday, excluding weekends) with cross-year boundary and overlap validation.
* **Pessimistic Concurrency Locking:** FIFO approval queue with atomic database transactions utilizing `SELECT ... FOR UPDATE` row locks, preventing race conditions and balance overdrafts.
* **Real-Time Push Notifications:** Sub-100ms event distribution via a Ratchet WebSocket daemon (:8090) backed by a reliable Transactional Outbox pattern.
* **Interactive API Portal:** Integrated Swagger UI accessible at `/docs` backed by OpenAPI 3.0 specification files.

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

### 1. PHPUnit Automated Test Suite (669 Tests)
Run the entire backend test suite:
```bash
./vendor/bin/phpunit
```
*Current Live Result:* **669 tests, 2,528 assertions, 0 errors, 0 failures, 94 skipped in sandboxed CLI (100% passing)**.

### 2. Code Style & Baseline Check
```bash
php bin/check-phpcs-baseline.php
```
*Requirement:* Must introduce **0 new violations** against the project baseline.

### 3. Selenium E2E Browser Automation
```powershell
cd e2e-tests
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
pytest tests/ -v
```

---

## 📄 License & Attribution
NEXORA HR is engineered and maintained as an enterprise human resource information system and data structures & algorithms workbench. All rights reserved.
