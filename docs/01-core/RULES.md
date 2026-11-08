# NEXORA HR — Engineering, Security & AI Agent Rules

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Mandatory Engineering Standard  
**Date:** November 2026  
**Audience:** Core Engineers, Technical Contributors, Code Reviewers, and AI Coding Agents  

---

## 1. Core Identity & Architectural Constraints

1. **Brand Identity:** The application name is **NEXORA HR**; its official tagline is **People. Processes. Progress.**
2. **Strict 3-Role Model:**
   * Exactly three application roles are supported: **`admin`**, **`hr`**, and **`employee`**.
   * `admin` possesses full system-wide administrative authority, user account provisioning, role assignment, account deactivation/reactivation, application settings, and audit monitoring.
   * `hr` retains workforce operational responsibilities (employee directory, departments, positions, leave review queue, approvals/rejections, leave balances, and reports). HR is strictly prohibited from managing application-wide role assignments, Admin provisioning, or security configurations.
   * `employee` retains isolated self-service capabilities (profile, leave filing, balance tracking, notifications) with ownership enforcement.
   * **Last Active Admin Protection:** Accidental deletion, deactivation, or demotion of the last active Administrator is strictly blocked at the model/controller level.
   * **Controlled Admin Provisioning:** Public registration of Admin accounts is prohibited; the initial Admin account is bootstrapped exclusively via the CLI provisioning tool (`bin/create_admin_user.php`).
3. **Single Source of Truth:**
   * Do not assume historical documentation or planning notes reflect current code.
   * Inspect actual files, schemas, and live test suites before asserting functionality.
   * Never document planned features as implemented. Clearly distinguish:
     * `[IMPLEMENTED]` Verified in codebase and passing tests.
     * `[PARTIALLY_IMPLEMENTED]` Core logic exists, edge cases or UI pending.
     * `[PLANNED]` Documented in roadmap, not yet authored.
     * `[DEPRECATED]` Superseded by architectural decisions.

---

## 2. Coding & Quality Standards

### 2.1 PHP Standards (PHP 8.2+)
* **Style Guide:** Code must conform to PSR-12 coding style. All commits must pass the baseline sniffer:
  ```bash
  php bin/check-phpcs-baseline.php
  ```
  New code must introduce **zero new violations**.
* **Type Safety:** Use explicit return types, parameter types, and union/nullable types on all class methods and functions.
* **Naming Conventions:**
  * Classes and Interfaces: `PascalCase` (e.g., `LeaveApprovalService`, `AuthMiddleware`).
  * Methods and Functions: `camelCase` (e.g., `verifyAuthenticatedAccountState`, `currentUser`).
  * Variables and Properties: `camelCase` (e.g., `$leaveRequest`, `$dbUser`).
  * Database Columns & Tables: `snake_case` (e.g., `leave_balances`, `employee_no`).
  * View Templates: `snake_case.php` (e.g., `employee_profile.php`).
* **Zero Debug Artifacts:** Never leave `var_dump()`, `print_r()`, `dd()`, `die()`, or commented-out code blocks in committed files. Use `error_log()` for server-side diagnostics.

### 2.2 Database Standards (MySQL 8.0 InnoDB)
* **Engine & Collation:** All tables must utilize `ENGINE=InnoDB` and `DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
* **Referential Integrity:** Always define explicit foreign keys. Default to `ON DELETE RESTRICT ON UPDATE CASCADE` for foundational parent records (e.g., departments, positions, employees) to prevent orphan records.
* **Prepared Statements:** Direct query string concatenation with dynamic user input is strictly prohibited. Use PDO prepared statements with explicit parameter binding for every query.
* **Composite Indexes:** Always index columns used in `WHERE`, `JOIN`, and `ORDER BY` clauses (e.g., `(user_id, is_read)` on `notifications`, `(employee_id, year)` on `leave_balances`).
* **Migration Idempotency:** Migrations must reside in `database/migrations/` using timestamp prefixes (`YYYY_MM_DD_descriptive_name.sql`) and include safe existence checks (`CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`).

### 2.3 Frontend & UI Standards
* **Vanilla Architecture:** Maintain pure vanilla JavaScript (ES6+) and modern semantic HTML5/CSS3. Avoid introducing heavy external frameworks (React, Vue, jQuery) into the core web portal.
* **Output Sanitization:** All dynamic values rendered in HTML views must pass through the `e()` sanitization helper (`htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`).
* **Accessible Semantics:** Follow WCAG 2.1 AA standards. Ensure all interactive buttons, modals, and forms include appropriate `aria-*` attributes, focus indicators, and semantic HTML elements (`<button>`, `<header>`, `<nav>`, `<main>`).

---

## 3. Mandatory Security Rules

### 3.1 Authentication & Multi-Factor Security
* **Password Hashing:** Passwords must be hashed using `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])`. Never use MD5, SHA-1, or plain SHA-256 for passwords.
* **Mandatory 2FA/MFA:** Sign-in flows must require two-factor verification (Email OTP or RFC 6238 TOTP). Never bypass the MFA step in production routes.
* **Session Fixation Prevention:** Always call `session_regenerate_id(true)` upon successful authentication and MFA completion.
* **Continuous State Re-validation:**
  * Every authenticated request must pass through `Session::verifyAuthenticatedAccountState()`.
  * If a user is deactivated, their employee record is terminated, their role changes, or their password hash changes, terminate the session immediately and redirect to `/login`.
  * Synchronize user display attributes (`name`, `employee_no`, `department`, `position`) with the live database on every request.
* **Step-Up Authentication:** High-impact operations (e.g., employee termination, administrative quota resets) require password re-verification if the user's last authentication occurred more than 15 minutes prior.

### 3.2 Authorization & Route Protection
* **Fail-Closed Default:** Unprotected routes are strictly prohibited. Every endpoint must declare explicit middleware:
  * Public: `/login`, `/verify-otp`, `/logout`
  * HR Only: `[AuthMiddleware::class, $hrOnly]`
  * Employee Only: `[AuthMiddleware::class, $employeeOnly]`
* **Ownership Enforcement:** Endpoints handling employee self-service (`/employee/*`, `/leaves/*`) must verify that `$_SESSION['user']['employee_id']` matches the requested record ID. Cross-record tampering must immediately yield `HTTP 403 Forbidden`.

### 3.3 CSRF & Input Protection
* **Synchronizer Token:** All state-altering requests (`POST`, `PUT`, `DELETE`) must include a valid CSRF token in the `csrf_token` form field or `X-CSRF-TOKEN` HTTP header.
* **Strict Validation:** Input payloads must be strictly validated for format, length, and allowed values before passing to models or services.

---

## 4. Operational & Environment Safety

### 4.1 Prohibited Actions
> [!CAUTION]
> The following operations are strictly forbidden without explicit, written authorization from the engineering lead:
> 1. Running `git clean -fd`, `git reset --hard`, or force-pushing (`git push --force`).
> 2. Running `docker compose down -v` or `docker volume prune` that destroys persistent database volumes (`db_data`).
> 3. Running destructive SQL operations (`DROP DATABASE`, `DROP TABLE`, `TRUNCATE`, or `DELETE` without a qualified `WHERE` clause).
> 4. Committing `.env` files, production credentials, private keys, or webhook secrets.
> 5. Bypassing authentication or authorization checks in test harnesses or application code.

### 4.2 Test Database Isolation
* **Separation of Concerns:** Automated tests must run exclusively against the isolated test database (`hris_test`).
* **Never Point Tests to Production:** Test suites must never execute against `hris_db` or production connection strings.
* **Automated Cleanup:** Integration and feature tests must execute within database transactions that roll back upon completion, or clean up seeded records in `tearDown()`.

---

## 5. Instructions for AI Coding Agents

When operating inside this repository, AI agents must strictly observe the following workflow:

1. **Read-Before-Write:**
   * Always read relevant model, controller, view, and migration files before proposing or writing code.
   * Check `MEMORY.md` first for the repository context, key file map, and active constraints.
2. **Preserve User Changes:**
   * Inspect `git status` before touching files.
   * Never overwrite, discard, or revert uncommitted changes made by the user.
3. **Verify With Real Tests:**
   * After making changes, always run the PHPUnit test suite:
     ```bash
     ./vendor/bin/phpunit
     ```
   * Run the PSR-12 baseline check:
     ```bash
     php bin/check-phpcs-baseline.php
     ```
   * Report actual test execution counts, assertion counts, and exit codes. Never invent test results.
4. **Scope Discipline:**
   * Restrict modifications strictly to the files requested by the task.
   * Do not arbitrarily refactor working components, reformat unrelated files, or alter project architectural patterns.

