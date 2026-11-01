# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Human Resources Administrators (`hr`)**: Enterprise HR managers responsible for managing employee master records, organizational departments, job positions, annual leave quotas, reviewing and approving/rejecting leave applications under strict ACID concurrency, auditing compliance logs, and generating operational reports.
- **Employees (`employee`)**: Workforce users accessing the self-service portal to check vacation and sick leave entitlements, submit leave requests with automated business-day calculations, receive real-time push alerts on approval status, and manage profile credentials.
- **Academic / Technical Users**: Engineering students and instructors utilizing the secondary in-memory Computer Science Data Structures & Algorithms (DSA) laboratory for algorithm benchmarking and educational demonstration.

## Product Purpose

The product provides a reliable, secure, and transactional HR Information and Leave Management web system. It eliminates leave balance race conditions through ACID-guaranteed approval queues, enforces strict role separation with mandatory two-factor email OTP authentication, and streamlines day-to-day employee leave workflows. Success is defined by zero balance overdrafts, sub-second transactional approval workflows, error-free auditing, and clear, accessible self-service for all employees.

## Positioning

An enterprise-grade, native-stack HRIS engineered for operational integrity, pairing pessimistic row locking (`SELECT ... FOR UPDATE`) FIFO leave approvals and real-time WebSocket notifications with an integrated computer science algorithmic benchmark laboratory.

## Operating Context

- **Environment**: Internal corporate intranet / web application accessed across desktop workstations and mobile web browsers.
- **Usage Rituals**: Daily self-service leave balance checks, rapid mobile leave filings, periodic HR review batches during business hours, and annual organizational reporting.
- **Architecture**: Multi-tier containerized stack (PHP 8.2–8.5, Apache/Nginx reverse proxy, MySQL 8.0 InnoDB, Ratchet WebSocket server on port 8090).

## Capabilities and Constraints

- **Strict 2-Role RBAC**: Mutually exclusive authenticated sessions and navigation structures for `hr` and `employee` roles.
- **Two-Factor Authentication (2FA)**: Mandatory 6-digit numeric OTP delivered via SMTP email with rate-limiting and atomic single-use consumption.
- **Working-Day Leave Engine**: Automated calculation of business days (Monday through Friday, excluding weekends) with collision prevention and balance checks.
- **ACID Transactional Approvals**: FIFO review queue backed by pessimistic database row locking to prevent concurrent balance depletion.
- **Real-Time Push Notifications**: Low-latency notification dispatch powered by Ratchet WebSockets (`/ws`) and a Transactional Outbox pattern.
- **Frontend Stack**: Server-rendered native PHP views with vanilla JavaScript, modern CSS custom properties, and responsive component cards (no heavy client-side frameworks).

## Brand Commitments

- **Product Name**: Employee HRIS & Leave Management System.
- **Tone & Voice**: Professional, reliable, clear, and reassuring enterprise utility.
- **Visual Identity**: Indigo accent (`#4F46E5`), slate neutrals (`#0F172A`, `#64748B`, `#F8FAFC`), crisp tabular layouts, and semantic status indicators (Emerald for Approved, Amber for Pending, Rose for Rejected).

## Evidence on Hand

- Fully implemented backend with 357 passing unit and integration tests (`tests/`).
- Automated end-to-end Selenium test suite with 29 passing test cases across Brave and Chrome (`e2e-tests/`).
- Architectural specifications and schema definitions documented in `PROJECT_DOCUMENTATION.md` and `README.md`.
- Phase 4 UI/UX screen specifications in `docs/phase-4-ui-ux/`.

## Product Principles

1. **Integrity Before Convenience**: Employee data and leave balances must remain strictly consistent at all times; race conditions, duplicate submissions, and negative balance states are prevented at both the database and UI layers.
2. **Scanability and Task Efficiency**: Interfaces in `Operate` mode prioritize clarity, low cognitive load, rapid form completion, and instant visual identification of request statuses.
3. **Unambiguous Feedback**: Every action—whether a submission, review, authentication challenge, or error—must provide immediate, accessible, and human-readable feedback.
4. **Strict Boundary Segregation**: HR administrative oversight and employee self-service actions remain visually and architecturally segregated to prevent operational confusion or unintended privilege exposure.

## Accessibility & Inclusion

- Compliance with WCAG 2.1 Level AA standards, ensuring minimum 4.5:1 text contrast ratios, visible `:focus-visible` indicators, complete keyboard navigability, and appropriate ARIA attributes for live alerts and form controls.

