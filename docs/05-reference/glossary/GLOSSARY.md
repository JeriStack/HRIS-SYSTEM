# NEXORA HR — Terminology & Domain Glossary

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Glossary  
**Date:** November 2026  
**Document Owner:** Repository Maintainer & Technical Architecture Team  

---

## 1. Domain & Architectural Terms

| Term / Acronym | Definition & System Context |
|---|---|
| **NEXORA HR** | Official name of the platform. Tagline: *People. Processes. Progress.* |
| **HRIS** | Human Resource Information System. Software supporting workforce administration, organizational hierarchy, and employee data management. |
| **RBAC** | Role-Based Access Control. Security model governing endpoint access. NEXORA HR enforces strictly **two roles**: **`hr`** and **`employee`**. |
| **MFA** | Multi-Factor Authentication. Two-step sign-in process requiring primary password credentials followed by a secondary verification challenge. |
| **Email OTP** | One-Time Password delivered via SMTP email. Consists of a cryptographically secure 6-digit numeric token with a 10-minute validity window. |
| **TOTP** | Time-based One-Time Password. Open standard (RFC 6238) generating rotating 6-digit codes via authenticator mobile apps (e.g., Google Authenticator). |
| **Step-Up Authentication** | Elevated security checkpoint requiring users to re-confirm credentials before executing sensitive actions (e.g., employee termination). |
| **Auth Fingerprint** | Cryptographic SHA-256 hash computed over `id|role|status|empStatus|passwordHash`. Re-validated on every HTTP request by `Session::verifyAuthenticatedAccountState()`. |
| **FIFO** | First-In, First-Out. Queue ordering used in the HR leave approval console to ensure older pending applications are processed before newer submissions. |
| **Pessimistic Locking** | Database concurrency control utilizing `SELECT ... FOR UPDATE` row locks inside atomic transactions to prevent double-deductions and negative balances. |
| **Transactional Outbox** | Distributed systems pattern where real-time domain events are committed to an `outbox_events` table inside the business transaction, ensuring zero loss of event telemetry. |
| **Ratchet** | Asynchronous PHP WebSocket server framework built on ReactPHP, powering NEXORA HR's real-time browser push notifications on port `:8090`. |
| **Ownership Validation** | Authorization enforcement ensuring that employees accessing self-service endpoints can only query and mutate records linked to their own employee ID. |
| **CSRF** | Cross-Site Request Forgery. Neutralized using the Synchronizer Token Pattern (`CsrfMiddleware`) on all state-altering HTTP methods. |
| **XSS** | Cross-Site Scripting. Neutralized by escaping dynamic template outputs using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` via the `e()` helper. |
| **RTM** | Requirements Traceability Matrix. Mapping between functional requirements, implementing components, and automated test cases. |
| **NFR** | Non-Functional Requirement. System constraints covering security, performance, reliability, and accessibility. |
| **OPcache** | PHP bytecode caching engine embedded in the runtime container to eliminate script compilation overhead on repetitive requests. |

