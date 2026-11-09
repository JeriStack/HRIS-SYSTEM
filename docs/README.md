# NEXORA HR Documentation

People. Processes. Progress.

An enterprise-grade Human Resource Information System (HRIS) and Leave Management Platform engineered with native PHP 8.5, MySQL 8.0 (InnoDB), Ratchet WebSockets (:8090), and Docker Compose.

---

## 1. Core Documentation (`01-core/`)

Essential architectural, design, user manual, and product specifications governing NEXORA HR:

* **[Product Requirements](01-core/PRD.md)** (`01-core/PRD.md`)  
  Executive product vision, functional scope (FR-001 through FR-015), leave business rules, NFRs, and live Requirements Traceability Matrix (RTM).
* **[Three-Role User Manual](01-core/user-manual.md)** (`01-core/user-manual.md`)  
  Comprehensive step-by-step user manual for Administrators (`admin`), HR Officers (`hr`), and Employees (`employee`), including required permissions, validation messages, and screenshot references.
* **[System Architecture](01-core/ARCHITECTURE.md)** (`01-core/ARCHITECTURE.md`)  
  Custom 4-tier MVC framework, database architecture, row-level pessimistic locking (`SELECT ... FOR UPDATE`), Transactional Outbox pattern, and Mermaid architecture diagrams.
* **[Engineering Rules](01-core/RULES.md)** (`01-core/RULES.md`)  
  Mandatory 3-role model (`admin`, `hr`, and `employee`) under ADR-012, PSR-12 standards, security boundaries, operational safety guardrails, and AI coding agent guidelines.
* **[UI/UX Design](01-core/DESIGN.md)** (`01-core/DESIGN.md`)  
  Design tokens, Cobalt Blue / Slate palette, typography, spatial grid, responsive App Shell, component library, and WCAG 2.1 AA accessibility guidelines.
* **[Development Roadmap](01-core/TASK.md)** (`01-core/TASK.md`)  
  Comprehensive phase breakdown (Phases 1–16), verified test metrics (669 passing tests, 0 new PSR-12 violations), and live pre-release task tracking.
* **[AI Agent Memory](01-core/MEMORY.md)** (`01-core/MEMORY.md`)  
  High-density context primer, key file paths, core business rules, and essential CLI commands for AI assistants and context engineering.

---

## 2. Technical References, UML, Workflows & DSA (`02-technical/`)

Deep technical guides, verified diagrams, and formal specifications:

* **[System Architecture & Components](02-technical/architecture/system-architecture.md)** (`02-technical/architecture/system-architecture.md`)  
  Verified container architecture, Clean Architecture domain layer, component responsibilities, and end-to-end request/data flows.
* **[Database Documentation](02-technical/database/DATABASE.md)** & **[Editable Mermaid ERD](02-technical/database/erd.mmd)** (`02-technical/database/`)  
  18-table InnoDB schema catalog, relationships, composite indexes, foreign key constraints, migrations, transactions, and data integrity.
* **[UML Diagrams Catalog](02-technical/uml/uml-diagrams.md)** (`02-technical/uml/uml-diagrams.md`)  
  System Context, UML Use Case, Class, Sequence, Activity, Component, and Deployment diagrams reconciled with the actual codebase.
* **[System Workflows & Flowcharts](02-technical/workflows/system-workflows.md)** (`02-technical/workflows/system-workflows.md`)  
  End-to-end flowcharts for Authentication/MFA, Admin Governance, HR Operations, Employee Self-Service, Leave Submission/Approval/Cancellation, Outbox/Queue, and Tamper-Evident Audit Trail.
* **[DSA Documentation & Flowcharts](02-technical/dsa/dsa-documentation.md)** (`02-technical/dsa/dsa-documentation.md`)  
  Supplementary Computer Science Data Structures & Algorithms specifications, pseudocode, complexity analysis, flowcharts, and empirical benchmark comparisons.
* **[Skeleton Screen System](02-technical/skeleton-screen-system.md)** (`02-technical/skeleton-screen-system.md`)  
  Accessible SSR + CSR shimmer placeholder architecture (`SkeletonRenderer` and `skeleton.js`).
* **[Security Documentation](02-technical/security/SECURITY.md)** (`02-technical/security/SECURITY.md`)  
  Authentication, MFA (Email OTP & TOTP), sessions, RBAC, CSRF, sensitive HR data protection, and continuous live state verification (`verifyAuthenticatedAccountState`).
* **[Testing Documentation](02-technical/testing/TESTING.md)** (`02-technical/testing/TESTING.md`)  
  PHPUnit (669 tests), PHPStan, PHPCS baseline check, Selenium E2E with Brave/Chromium, test database isolation (`hris_test`), and regression procedures.
* **[API Reference](02-technical/api/API.md)** (`02-technical/api/API.md`)  
  REST API v1 endpoints catalog, HTTP methods, authentication, authorization, request/response formats, error envelopes, and OpenAPI / Swagger UI integration.
* **[Architecture Decisions](02-technical/decisions/DECISIONS.md)** (`02-technical/decisions/DECISIONS.md`)  
  Approved technical decisions, business-rule decisions, alternatives, consequences, and superseded decisions (ADR-001 through ADR-012).

---

## 3. Operations, Installation & Production Readiness (`03-operations/`)

Operational manuals, installation guides, deployment runbooks, and telemetry:

* **[Installation & Configuration Guide](03-operations/installation-and-configuration-guide.md)** (`03-operations/installation-and-configuration-guide.md`) — Verified Docker Compose and native XAMPP/Linux installation, migrations, and secure CLI Admin provisioning (`bin/create_admin_user.php`).
* **[Deployment Runbook](03-operations/deployment/DEPLOYMENT.md)** (`03-operations/deployment/DEPLOYMENT.md`) — Multi-container Docker Compose and Ubuntu 24.04 VPS production deployment instructions, SSL termination, and automated backups.
* **[Production Readiness Checklist](03-operations/production-readiness-checklist.md)** (`03-operations/production-readiness-checklist.md`) — 18-point production readiness and security verification matrix.
* **[Full-System Functional Audit Report](03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md)** (`03-operations/audits/FULL_SYSTEM_FUNCTIONAL_AUDIT.md`) — Complete Phase 15/16 functional, security, database, and regression audit report (`BUG-001` – `BUG-007`).
* **[DevOps](03-operations/devops/)** (`03-operations/devops/`) — CI/CD pipelines, container build scripts, Terraform configuration, and infrastructure automation.
* **[Monitoring](03-operations/monitoring/)** (`03-operations/monitoring/`) — Prometheus metric scrapers (:9090), Grafana dashboards (:3100), `nginx-exporter`, and `mysqld-exporter`.
* **[Troubleshooting](03-operations/troubleshooting/TROUBLESHOOTING.md)** (`03-operations/troubleshooting/TROUBLESHOOTING.md`) — Common issue diagnostics for containers, MySQL connection errors, OTP limits, session expirations, and test locks.

---

## 4. Quality Assurance & Performance Testing (`04-quality-assurance/` & `04-history/`)

* **[QA Test Plan & Verified Results](04-quality-assurance/test-plan-and-results.md)** (`04-quality-assurance/test-plan-and-results.md`) — Measured results for 669 PHPUnit tests, 2,528 assertions, PHPStan, and PSR-12 baseline check.
* **[Performance Test Plan & Results](04-quality-assurance/performance-test-plan-and-results.md)** (`04-quality-assurance/performance-test-plan-and-results.md`) — Reproducible latency percentiles (`avg`, `p50`, `p95`, `ops/sec`) and InnoDB index verification.
* **[Development Phases & Historical Records](04-history/)** (`04-history/`) — Milestone records and deliverables from Phase 1 through Phase 13.

---

## 5. Project Defense, Presentation, Reference & Screenshots (`05-defense/`, `05-reference/`, `screenshots/`)

* **[Project Defense Guide](05-defense/project-defense-guide.md)** (`05-defense/project-defense-guide.md`) — Background, problem statement, objectives, scope, and deep technical explanations for the defense panel.
* **[Anticipated Panel Questions & Answers](05-defense/anticipated-questions.md)** (`05-defense/anticipated-questions.md`) — Comprehensive Q&A bank grounded in verified source code.
* **[Live Demonstration Script](05-defense/demonstration-script.md)** (`05-defense/demonstration-script.md`) — 15-step live defense walkthrough with roles, routes, expected results, and backup plans.
* **[Final Presentation Outline & Speaker Notes](05-defense/presentation-outline.md)** (`05-defense/presentation-outline.md`) — 22-slide master presentation deck with speaker notes.
* **[Screenshots & Visual Evidence Catalog](screenshots/README.md)** (`screenshots/README.md`) — Verified UI captures and empirical DSA benchmark SVG graphs.
* **[Glossary](05-reference/glossary/GLOSSARY.md)** & **[RBAC Reference](05-reference/rbac/)** (`05-reference/`) — Domain terminology and role-permission matrix specifications.
