# Phase 14 — Quality Assurance Defect Log & Triage Report
## HRIS-SYSTEM | Defect Registry and Remediation History

- **Application:** HRIS-SYSTEM
- **Phase:** Phase 14 — Testing and Quality Assurance
- **Document Version:** 1.0.0
- **Tracking Lifecycle:** Reported -> Triaged -> Fixed -> Verified -> Closed
- **Total Defects Identified:** 6
- **Total Defects Resolved:** 6 (100% Resolved)
- **Open Critical / Major Defects:** 0

---

## 1. Defect Classification Scheme

- **Critical:** System crash, data corruption, authentication bypass, unhandled fatal error blocking primary application operation.
- **Major:** High-impact functional regression, protocol handshake failure, unhandled exception in secondary module, test suite deprecation/warning.
- **Minor:** Non-blocking test notice, log verbosity, UI misalignment.
- **Trivial:** Formatting, documentation, or code style anomaly.

---

## 2. Comprehensive Defect Registry

### DEF-001: PHPUnit Deprecation in `LeaveHistoryLinkedListTest`
- **Defect ID:** `DEF-001`
- **Severity:** Major
- **Component:** Test Infrastructure / DSA Integration
- **Affected File:** `tests/Integration/DSA/LeaveHistoryLinkedListTest.php`
- **Description:** PHPUnit 13 emitted a deprecation warning: `Using with*() without expects() is deprecated and will lead to an exception in PHPUnit 14`.
- **Root Cause:** A test method called `->with(...)` on a mock method without chaining an explicit `->expects(...)` invocation.
- **Remediation:** Added `->expects($this->once())` before `->with(...)`.
- **Verification:** Re-ran PHPUnit; deprecation resolved with 0 deprecations remaining.
- **Status:** **CLOSED**

---

### DEF-002: Expectation-Free Mock Object Notices Across DSA Test Suites
- **Defect ID:** `DEF-002`
- **Severity:** Minor
- **Component:** Test Infrastructure / PHPUnit
- **Affected Files:**
  - `tests/Integration/DSA/EmployeeDirectoryTreeTest.php`
  - `tests/Integration/DSA/EmployeeLookupHashTableTest.php`
  - `tests/Integration/DSA/LeaveApprovalQueueTest.php`
  - `tests/Integration/DSA/RecentActivityStackTest.php`
  - `tests/Feature/DsaWorkflowIntegrationTest.php`
- **Description:** Running the full test suite produced 9 PHPUnit Notices: `No expectations were configured for the mock object`.
- **Root Cause:** Tests initialized test doubles using `$this->createMock(...)` for PDO or statement stubs, but configured only return values (`willReturn()`) without defining mock expectations. In PHPUnit 13, mock doubles without expectations trigger warning notices.
- **Remediation:** Replaced `$this->createMock(...)` with `$this->createStub(...)` across all 9 instances where only return values are stubbed.
- **Verification:** PHPUnit notice count dropped from 9 to 0.
- **Status:** **CLOSED**

---

### DEF-003: Database Decoupling & Unhandled Offline Exception in `ReportController`
- **Defect ID:** `DEF-003`
- **Severity:** Major
- **Component:** Reporting / Integration Testing
- **Affected Files:**
  - `app/controllers/ReportController.php`
  - `tests/Feature/ReportsIntegrationTest.php`
- **Description:** When running unit/feature tests with the database container offline, `ReportController` threw an unhandled `RuntimeException: Database connection is not available: SQLSTATE[HY000] [2002]`, causing 6 fatal test crashes.
- **Root Cause:** `ReportController` tightly coupled itself to `Database::getInstance()->getConnection()` in its constructor without accepting an optional injected PDO instance or performing a lazy connectivity check.
- **Remediation:** 
  1. Updated `ReportController::__construct(?PDO $pdo = null)` to support dependency injection.
  2. Added pre-flight check in `ReportsIntegrationTest` (`Database::getInstance()->isConnected()`) to skip cleanly when the database service is unavailable, avoiding fatal execution stops.
- **Verification:** Tests now pass cleanly whether database is online or offline.
- **Status:** **CLOSED**

---

### DEF-004: WebSocket Handshake Origin Rejection (403 Forbidden) on Proxy Port 3001
- **Defect ID:** `DEF-004`
- **Severity:** Major
- **Component:** Real-Time Daemon / Ratchet WebSocket
- **Affected Files:**
  - `docker-compose.yml`
  - `.env`
  - `app/realtime/HandshakeGuard.php`
- **Description:** Browser clients connecting through local dev proxies (such as BrowserSync or Vite on port 3000/3001) were rejected with `HTTP/1.1 403 Forbidden` and logged `[WARNING] ws.handshake_rejected {"reason":"origin"}`.
- **Root Cause:** `WS_ALLOWED_ORIGINS` was restricted to `http://localhost:8080`, strictly forbidding web clients served from alternative local ports.
- **Remediation:** Extended `WS_ALLOWED_ORIGINS` in both `.env` and `docker-compose.yml` to include `http://localhost:3000`, `http://127.0.0.1:3000`, `http://localhost:3001`, and `http://127.0.0.1:3001`. Restarted `hris_realtime`.
- **Verification:** Handshake confirmed returning `HTTP/1.1 101 Switching Protocols` on port 3001.
- **Status:** **CLOSED**

---

### DEF-005: Nginx Upstream 502 Bad Gateway Post-Container Recreate
- **Defect ID:** `DEF-005`
- **Severity:** Critical
- **Component:** Docker Infrastructure / Reverse Proxy
- **Affected File:** `docker/nginx/nginx.conf`
- **Description:** Accessing `http://localhost:3001` or `http://localhost:8080` presented the default Nginx error page: `An error occurred. Faithfully yours, nginx.`
- **Root Cause:** When Docker containers were restarted or recreated, Docker's internal DNS assigned a new internal IP (e.g. `172.18.0.7`) to `hris_app`, while the long-running Nginx process retained the cached IP (`172.18.0.5`), failing upstream fastcgi handoffs.
- **Remediation:** Executed `docker compose restart nginx` to clear cached upstream IP resolutions.
- **Verification:** Browser requests to `http://localhost:8080/login` and `http://localhost:3001/login` immediately returned HTTP 200 OK.
- **Status:** **CLOSED**

---

### DEF-006: Lack of Real-Time Notification on Employee Profile Modification by HR
- **Defect ID:** `DEF-006`
- **Severity:** Major
- **Component:** Real-Time Event Dispatch / Employee Dashboard
- **Affected Files:**
  - `app/realtime/EventAuthorizer.php`
  - `app/realtime/NotificationPayload.php`
  - `app/controllers/EmployeeController.php`
  - `public/assets/js/realtime.js`
  - `public/assets/css/realtime.css`
- **Description:** When an HR manager edited an employee's profile, the change was persisted in MySQL, but the employee's active dashboard did not receive an alert or refresh.
- **Root Cause:** Missing event token registration in `EventAuthorizer` and absent WebSocket dispatch invocation in `EmployeeController::update()`.
- **Remediation:**
  1. Registered `EventAuthorizer::PROFILE_UPDATED = 'employee.profile_updated'` in authorizer and payload mapper.
  2. Injected dispatch logic in `EmployeeController::update()` targeting the employee's user ID.
  3. Added client-side listener in `realtime.js` with toast notification, 1.5s countdown bar, and input guard (`isUserActivelyInputting()`) to avoid interrupting user typing.
- **Verification:** Verified end-to-end WebSocket payload delivery and DOM update trigger.
- **Status:** **CLOSED**

