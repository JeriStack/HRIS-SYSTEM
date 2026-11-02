# NEXORA HR Documentation

People. Processes. Progress.

An enterprise-grade Human Resource Information System (HRIS) and Leave Management Platform engineered with native PHP 8.5, MySQL 8.0 (InnoDB), Ratchet WebSockets (:8090), and Docker Compose.

---

## 1. Core Documentation

Essential architectural, design, and product specifications governing NEXORA HR:

* **[Product Requirements](01-core/PRD.md)** (`01-core/PRD.md`)  
  Executive product vision, functional scope (FR-001 through FR-015), leave business rules, NFRs, and live Requirements Traceability Matrix (RTM).
* **[System Architecture](01-core/ARCHITECTURE.md)** (`01-core/ARCHITECTURE.md`)  
  Custom 4-tier MVC framework, database architecture, row-level pessimistic locking (`SELECT ... FOR UPDATE`), Transactional Outbox pattern, and Mermaid architecture diagrams.
* **[Engineering Rules](01-core/RULES.md)** (`01-core/RULES.md`)  
  Mandatory 2-role model (`hr` and `employee`), PSR-12 standards, security boundaries, operational safety guardrails, and AI coding agent guidelines.
* **[UI/UX Design](01-core/DESIGN.md)** (`01-core/DESIGN.md`)  
  Design tokens, Cobalt Blue / Slate palette, typography, spatial grid, responsive App Shell, component library, and WCAG 2.1 AA accessibility guidelines.
* **[Development Roadmap](01-core/TASK.md)** (`01-core/TASK.md`)  
  Comprehensive phase breakdown (Phases 1–11), verified test metrics (592 passing tests, 0 new PSR-12 violations), and live pre-release task tracking.
* **[AI Agent Memory](01-core/MEMORY.md)** (`01-core/MEMORY.md`)  
  High-density context primer, key file paths, core business rules, and essential CLI commands for AI assistants and context engineering.

---

## 2. Technical References

Deep technical guides and formal specifications:

* **[Security Documentation](02-technical/security/SECURITY.md)** (`02-technical/security/SECURITY.md`)  
  Authentication, MFA (Email OTP & TOTP), sessions, RBAC, CSRF, sensitive HR data protection, and continuous live state verification (`verifyAuthenticatedAccountState`).
* **[Database Documentation](02-technical/database/DATABASE.md)** (`02-technical/database/DATABASE.md`)  
  13-table InnoDB schema catalog, relationships, composite indexes, foreign key constraints, migrations, transactions, and data integrity.
* **[Testing Documentation](02-technical/testing/TESTING.md)** (`02-technical/testing/TESTING.md`)  
  PHPUnit (592 tests), PHPStan, PHPCS baseline check, Selenium E2E with Brave/Chromium, test database isolation (`hris_test`), and regression procedures.
* **[API Reference](02-technical/api/API.md)** (`02-technical/api/API.md`)  
  REST API v1 endpoints catalog, HTTP methods, authentication, authorization, request/response formats, error envelopes, and OpenAPI / Swagger UI integration.
* **[Architecture Decisions](02-technical/decisions/DECISIONS.md)** (`02-technical/decisions/DECISIONS.md`)  
  Approved technical decisions, business-rule decisions, alternatives, consequences, and superseded decisions (ADR-001 through ADR-011).

---

## 3. Operations

Operational manuals, deployment runbooks, and telemetry:

* **[Deployment](03-operations/deployment/DEPLOYMENT.md)** (`03-operations/deployment/DEPLOYMENT.md`) — Multi-container Docker Compose and Ubuntu 24.04 VPS production deployment instructions, SSL termination, and automated backups.
* **[DevOps](03-operations/devops/)** (`03-operations/devops/`) — CI/CD pipelines, container build scripts, Terraform configuration, and infrastructure automation.
* **[Monitoring](03-operations/monitoring/)** (`03-operations/monitoring/`) — Prometheus metric scrapers (:9090), Grafana dashboards (:3000), `nginx-exporter`, and `mysqld-exporter`.
* **[QA](03-operations/qa/)** (`03-operations/qa/`) — Test plans, test cases, verification matrices, and QA sign-off logs.
* **[Audits](03-operations/audits/)** (`03-operations/audits/`) — Technical audit reports, repair logs, and progress verification audits.
* **[Troubleshooting](03-operations/troubleshooting/TROUBLESHOOTING.md)** (`03-operations/troubleshooting/TROUBLESHOOTING.md`) — Common issue diagnostics for containers, MySQL connection errors, OTP limits, session expirations, and test locks.

---

## 4. Project History

Historical development phases, academic computer science documentation, and archived specifications:

* **[Development Phases](04-history/phases/)** (`04-history/phases/`) — Milestone records and deliverables from Phase 1 through Phase 13.
* **[DSA Documentation](04-history/dsa/)** (`04-history/dsa/`) — Computer Science Data Structures & Algorithms laboratory specifications, complexity analyses, and benchmarking engine.
* **[Historical Records](04-history/archive/PROJECT_DOCUMENTATION_HISTORICAL.md)** (`04-history/archive/PROJECT_DOCUMENTATION_HISTORICAL.md`) — Preserved monolithic development specification.
* **[Requirements Traceability](04-history/archive/requirements-traceability.md)** (`04-history/archive/requirements-traceability.md`) — Archived Phase 1 requirements traceability matrix (superseded by `01-core/PRD.md`).

---

## 5. Reference

Domain dictionaries and authorization models:

* **[Glossary](05-reference/glossary/GLOSSARY.md)** (`05-reference/glossary/GLOSSARY.md`) — Domain & system terminology glossary covering NEXORA HR, RBAC, FIFO, Pessimistic Locking, Transactional Outbox, MFA, and Auth Fingerprint.
* **[RBAC Reference](05-reference/rbac/)** (`05-reference/rbac/`) — Role-Based Access Control matrix, permission rules, route protection specifications, and ownership boundaries.
