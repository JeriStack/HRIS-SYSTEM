# NEXORA HR — Troubleshooting & Diagnostics Runbook

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Troubleshooting Reference  
**Date:** November 2026  
**Document Owner:** DevOps, Support Engineering & Quality Assurance  

---

## 1. Quick Diagnostic Checklist

When investigating issues in NEXORA HR, perform this initial health check:

```bash
# 1. Check Docker container health
docker compose ps

# 2. Check application and PHP error logs
docker compose logs app --tail=50

# 3. Check WebSocket daemon logs
docker compose logs realtime --tail=50

# 4. Check MySQL database connection and logs
docker compose logs db --tail=50

# 5. Run PHPUnit test suite to verify baseline integrity
./vendor/bin/phpunit
```

---

## 2. Common Issues & Solutions

### 2.1 Database & Connection Issues

#### Issue: `SQLSTATE[HY000] [2002] Connection refused`
* **Cause:** The MySQL container is still initializing or port 3306/3307 is blocked.
* **Resolution:**
  1. Inspect container status: `docker compose ps db`. If status is `starting`, wait 10 seconds for the healthcheck to pass.
  2. Verify that host port 3307 is not occupied by another MySQL/MariaDB service (e.g., local XAMPP MySQL):
     ```powershell
     Get-NetTCPConnection -LocalPort 3307 -ErrorAction SilentlyContinue
     ```
  3. Verify `.env` credentials match `docker-compose.yml`:
     ```ini
     DB_HOST=127.0.0.1
     DB_PORT=3307
     DB_DATABASE=hris_db
     DB_USERNAME=root
     DB_PASSWORD=rootpassword
     ```

#### Issue: MySQL Error `#1091 - Can't DROP '...'; check that column/key exists`
* **Cause:** Attempting to drop an index or column on a database (e.g., `hris_test`) that was never migrated or where the index does not exist.
* **Resolution:** No action needed. The error confirms the database is already clean.

---

### 2.2 Authentication & Session Issues

#### Issue: "Your session is no longer valid due to an account or security update."
* **Cause:** Triggered by `Session::verifyAuthenticatedAccountState()` when:
  1. The user account status was changed from `Active` to `Inactive`.
  2. The employee's `employment_status` was set to `Inactive` or `Terminated`.
  3. The user's role was altered.
  4. The password hash was updated.
* **Resolution:** Re-activate the employee record in the HR directory or sign in again with updated credentials.

#### Issue: "Too many login attempts. Please try again in 15 minutes."
* **Cause:** Exceeded the rate limit of 5 consecutive failed login attempts or 5 OTP requests within 15 minutes.
* **Resolution:**
  1. Wait for the 15-minute rolling window to elapse, OR
  2. Reset the challenge history directly in the development database:
     ```sql
     DELETE FROM email_otps WHERE user_id = :user_id;
     ```

#### Issue: HTTP 419 "Page Expired" on Form Submission
* **Cause:** Missing or expired CSRF token. Occurs if a user leaves a form open past the 30-minute session inactivity limit.
* **Resolution:** Refresh the page to obtain a fresh CSRF token embedded via `<?= csrf_field() ?>`.

---

### 2.3 Real-Time WebSocket & Notification Issues

#### Issue: Notification Status Dot Remains Amber ("Connecting…")
* **Cause:** Browser cannot establish a connection to `ws://localhost:8080/ws`.
* **Resolution:**
  1. Verify the `realtime` container is running:
     ```bash
     docker compose ps realtime
     ```
  2. Restart the WebSocket daemon:
     ```bash
     docker compose restart realtime
     ```
  3. Inspect Nginx proxy logs for WebSocket upgrade failures:
     ```bash
     docker compose logs web | grep ws
     ```

#### Issue: Notifications Inserted but Not Appearing in Browser
* **Cause:** The Transactional Outbox worker is not running to process `outbox_events`.
* **Resolution:**
  1. Check the `outbox_events` table for records with `status = 'PENDING'`:
     ```sql
     SELECT COUNT(*) FROM outbox_events WHERE status = 'PENDING';
     ```
  2. Trigger the outbox worker manually:
     ```bash
     docker compose exec app php bin/outbox-worker.php
     ```

---

### 2.4 Code Style & Testing Issues

#### Issue: `check-phpcs-baseline.php` Fails with New Violations
* **Cause:** Added PHP methods in snake_case (e.g., `test_my_feature()`) or mixed Windows CRLF line endings.
* **Resolution:**
  1. Run PHP Code Beautifier and Fixer:
     ```bash
     ./vendor/bin/phpcbf
     ```
  2. Ensure method names use `camelCaps` (e.g., `testMyFeature()`).
  3. Re-run verification: `php bin/check-phpcs-baseline.php`.

