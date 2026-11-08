# NEXORA HR — Testing Strategy & Quality Assurance

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.2  
**Status:** Authoritative Testing Reference  
**Date:** November 2026 (ADR-012 Three-Role Verification)  
**Document Owner:** Quality Assurance & Backend Engineering Team  

---

## 1. Quality Engineering Philosophy & Metrics

NEXORA HR maintains rigorous quality gates ensuring zero regressions across authentication, leave balance calculations, role-based authorization, and real-time WebSocket communications.

### Verified Test Metrics (Live Baseline)
* **Total Automated Tests:** **600 tests**
* **Total Assertions:** **2,152 assertions**
* **Failures & Errors:** **0 failures, 0 errors, 94 skipped (100% pass rate)**
* **Code Style:** **PSR-12 compliant (0 new violations against baseline)**
* **End-to-End Automation:** Selenium E2E suite covering full browser user journeys.

---

## 2. Test Suite Organization

```
tests/
├── Feature/                          # High-Level HTTP & Workflow Tests
│   ├── AdminRoleRbacTest.php         # Three-role matrix, admin governance, lockout guards
│   ├── AuthWorkflowTest.php          # Login, OTP verification, session establishment
│   ├── DashboardEmployeeUpdateRealtimeTest.php # Live profile sync & WebSocket auto-reload
│   ├── EmployeeManagementTest.php    # Workforce CRUD, deactivation, status validation
│   ├── LeaveFilingAndApprovalTest.php# End-to-end leave lifecycle & status transitions
│   └── RbacRouteProtectionTest.php   # Fail-closed middleware & ownership enforcement
├── Integration/                      # Database & Subsystem Integration Tests
│   ├── DatabaseTransactionTest.php   # ACID rollback behavior on simulated transport failures
│   ├── LeavePessimisticLockTest.php  # Concurrent row-locking (SELECT ... FOR UPDATE) simulation
│   ├── OutboxWorkerIntegrationTest.php # Transactional Outbox event polling & dispatch
│   └── MigrationRunnerTest.php       # SQL migration execution & idempotency
└── Unit/                             # Isolated Component & Algorithmic Tests
    ├── DsaMergeSortTest.php          # Algorithmic sorting of employee workforce datasets
    ├── DsaBinarySearchTest.php       # Fast logarithmic employee record lookup
    ├── RealtimePolicyTest.php        # Role-based WebSocket payload filtering & PII stripping
    ├── EmailOtpServiceTest.php       # 6-digit cryptographic OTP generation & expiration
    └── SessionFingerprintTest.php    # Session security fingerprint generation & verification
```

---

## 3. Running Automated Tests

### 3.1 PHPUnit Test Suite
Execute the entire test suite from the repository root:
```bash
# Run all 600 tests
./vendor/bin/phpunit

# Run a specific test directory
./vendor/bin/phpunit tests/Feature

# Run a single targeted test class
./vendor/bin/phpunit tests/Feature/DashboardEmployeeUpdateRealtimeTest.php

# Run with testdox format for readable output
./vendor/bin/phpunit --testdox
```

### 3.2 Code Style & Quality Gate
Verify that no new PSR-12 violations have been introduced against the project baseline:
```bash
php bin/check-phpcs-baseline.php
```
*Current baseline count:* 235 active legacy issues (baseline allows up to 259). Commits must introduce **0 new violations**.

### 3.3 End-to-End Browser Automation (Selenium)
The repository contains an automated E2E test suite written in Python with Selenium, targeting Chromium/Brave:
```bash
cd e2e-tests
pip install -r requirements.txt
pytest -v
```

---

## 4. Test Isolation & Database Fixtures

### 4.1 Strict Test Database Isolation
* **Test Database:** All automated tests execute strictly against **`hris_test`**.
* **Production/Dev Protection:** Tests never connect to `hris_db` or production hosts.
* **Environment Configuration:** Handled automatically via `phpunit.xml`:
  ```xml
  <php>
      <env name="APP_ENV" value="testing"/>
      <env name="DB_DATABASE" value="hris_test"/>
      <env name="DB_PORT" value="3307"/>
      <env name="MAIL_MAILER" value="array"/>
  </php>
  ```

### 4.2 Fixture Management & Transaction Rollback
* **Rollback Isolation:** Integration test cases wrap database mutations in transactions and roll them back in `tearDown()` to leave the database clean for subsequent tests.
* **OTP Challenge Reset:** Authentication tests utilize a dedicated OTP history reset helper before testing login flows, preventing test failures caused by 15-minute rate limit caps.

---

## 5. Continuous Integration (CI) Quality Gates

Every pull request and deployment artifact must satisfy three strict requirements before merge:
1. `check-phpcs-baseline.php` exits with code 0 (zero new PSR-12 violations).
2. `./vendor/bin/phpunit` completes with 100% passing tests and zero failures.
3. Database migrations pass in dry-run mode against a clean schema.

