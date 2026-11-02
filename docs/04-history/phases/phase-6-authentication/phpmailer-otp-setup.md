# Email OTP Two-Factor Authentication & Account Governance Guide

## 1. Overview & Architecture

The **Employee HRIS & Leave Management System** employs an enterprise-grade **Email Address + Password → Email OTP Verification → Role-Based Dashboard** authentication flow using **PHPMailer** and **SMTP**.

```
[ 1. User Enters Registered Email & Password ]
                      |
                      v
[ 2. Verify Credentials & Active Account Status ]
                      |
          +-----------+-----------+
          | (Failed)              | (Success)
          v                       v
    [ Reject & Log ]    [ 3. Generate 6-Digit Cryptographic OTP (random_int) ]
                                |
                                v
                        [ 4. Store BCRYPT Hashed OTP in email_otps (5-min expiry) ]
                                |
                                v
                        [ 5. PHPMailer Dispatches Branded HTML & Text Email ]
                                |
                                v
                        [ 6. Prompt User on /verify-otp Screen (60s Resend Cooldown) ]
                                |
                      +---------+---------+
                      | (Fail / >5 tries) | (Correct Code)
                      v                   v
                [ Invalidate OTP ]  [ 7. Atomically Consume OTP (Row Lock) ]
                [ Block Session  ]        |
                                          v
                                    [ 8. Regenerate Session ID (session_regenerate_id) ]
                                          |
                                          v
                                    [ 9. Enforce Role: HR or Employee Only ]
                                          |
                                +---------+---------+
                                |                   |
                                v                   v
                        [ /hr/dashboard ]   [ /employee/dashboard ]
```

---

## 2. Strict Two-Role Architecture

The application enforces a strict **2-Role Architecture**:
1. **HR (`hr`)** — Human Resources management dashboard, workforce administration, employee records, onboarding & user account provisioning, department & position management, leave approval, balance adjustments, and audit reports.
2. **Employee (`employee`)** — Employee self-service dashboard, personal profile viewing, leave filing, balance tracking, and self-service cancellation of pending requests.

> [!IMPORTANT]
> There is **no Admin application role or Admin dashboard**. Access is restricted strictly to HR and Employee accounts.
> All demonstration interfaces, demo buttons (`hr01`, `emp004`), quick-login scripts, sample credentials, and bypass shortcuts have been completely eliminated from the codebase.

---

## 3. Database Schema & Migration

### Migrations

1. **`2026_10_17_create_email_otps_table.sql`**: Creates the `email_otps` table for secure, hashed, expiring one-time passwords.
2. **`2026_10_18_add_email_to_users_table.sql`**: Adds the `email VARCHAR(100)` column and `uq_users_email` unique constraint to the `users` table, normalizing and backfilling from existing `employees.email` records.

### Running Migrations via CLI

A dedicated automated migration runner is available:

```bash
# Execute on Host Machine:
php bin/migrate.php

# Or execute inside Docker Application Container:
docker compose exec app php bin/migrate.php
```

### Remediation Procedure for Duplicate or Missing Emails

The schema strictly enforces email uniqueness across both `employees` (`uq_employees_email`) and `users` (`uq_users_email`). If an existing or imported database has missing or duplicate email addresses:

1. **Detection Query**:
   ```sql
   -- Find duplicate emails in employees
   SELECT email, COUNT(*) AS occurrences 
   FROM employees 
   GROUP BY email 
   HAVING occurrences > 1;

   -- Find missing or invalid emails
   SELECT id, employee_no, first_name, last_name, email 
   FROM employees 
   WHERE email IS NULL OR email = '' OR email NOT LIKE '%@%.%';
   ```

2. **Safe Remediation (Non-Destructive)**:
   - Contact affected employees or HR staff to obtain their actual real email addresses.
   - Update records individually using parameterized statements:
     ```sql
     UPDATE employees SET email = 'employee.actual@company.com' WHERE id = :id;
     UPDATE users SET email = 'employee.actual@company.com' WHERE employee_id = :id;
     ```
   - **Never** silently generate fake or placeholder email addresses to satisfy database constraints.

---

## 4. User Account Provisioning & Onboarding

### HR Portal Account Provisioning

New employees and accounts must be onboarded through authorized workflows:

1. **Create Employee**: Authenticated HR staff register the employee record under `/hr/employees/create`. The employee must have a unique, valid email address.
2. **Provision User Account**:
   - Navigate to **User Account & Role Management** (`/hr/users`) and click **+ Provision User Account** (`/hr/users/create`).
   - Alternatively, on the employee profile (`/hr/employees/{id}`), click **+ Provision Portal Account**.
   - Select the unprovisioned employee from the dropdown.
   - Choose the authorized application role (`employee` or `hr`).
   - Enter an initial password (minimum 8 characters).
   - Passwords are encrypted using `password_hash($password, PASSWORD_BCRYPT)`.
   - The user account is activated and ready for sign-in via Email OTP.

### First HR Account Setup via CLI

When deploying to a clean database with no existing HR accounts, an initial HR account must be created without opening unauthenticated public registration endpoints:

```bash
# Interactive mode (prompts for email, names, password):
php bin/create_hr_user.php

# Argument-based mode:
php bin/create_hr_user.php \
  --email="hr.officer@company.com" \
  --first-name="Eleanor" \
  --last-name="Vance" \
  --password="SuperSecureInitialPassword123"
```

> [!NOTE]
> `bin/create_hr_user.php` strictly verifies `php_sapi_name() === 'cli'`. Web browser requests are rejected with `403 Forbidden`.

---

## 5. PHPMailer & SMTP Configuration

### Environment Setup (`.env`)

Configure your SMTP settings in `.env` (copied from `.env.example`):

```env
# Email Delivery & PHPMailer SMTP Configuration
MAIL_DRIVER=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your-sender@gmail.com
SMTP_PASSWORD=your-16-char-google-app-password
MAIL_FROM_ADDRESS=your-sender@gmail.com
MAIL_FROM_NAME="HRIS System"
MAIL_TIMEOUT=15
MAIL_DEBUG=0
```

### Gmail App Password Setup (STARTTLS Port 587)

To use Gmail SMTP securely:
1. Log in to your Google Account and navigate to **Security** ([https://myaccount.google.com/security](https://myaccount.google.com/security)).
2. Ensure **2-Step Verification** is turned **ON**.
3. Navigate to **App passwords** ([https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)).
4. Create a new App Password for app name `HRIS-SYSTEM`.
5. Copy the generated 16-character code (e.g., `abcd efgh ijkl mnop`).
6. Set `SMTP_USERNAME` to your full Gmail address and `SMTP_PASSWORD` to the 16-character string without spaces.
7. Ensure `SMTP_PORT=587` and `SMTP_ENCRYPTION=tls`.

### Safe SMTP Diagnostics CLI Tool

To inspect SMTP connection and verify live delivery without exposing passwords:

```bash
# 1. Test socket connection and inspect configuration:
php bin/test_smtp.php

# 2. Dispatch a live test verification code to a real inbox:
php bin/test_smtp.php your-actual-email@gmail.com
```

---

## 6. Security Guarantees & OTP Policy

| Policy | Implementation | Description |
| :--- | :--- | :--- |
| **Login Identifier** | Registered Email | Authenticates against `users.email` and linked `employees.email`. Case-insensitive and trimmed. |
| **OTP Format** | 6 Numeric Digits | Generated via `random_int(0, 999999)` with zero-padding. |
| **Lifetime** | 5 Minutes (300s) | Server rejects any verification attempt after expiry. |
| **Attempt Limit** | 5 Attempts Max | Automatically marks challenge as consumed after 5 failed attempts. |
| **Resend Cooldown** | 60 Seconds | Enforced server-side. Prevents email flooding. |
| **Storage at Rest** | Hashed (BCRYPT) | Plaintext OTP is **never** written to database or logs. |
| **Single-Use** | Atomic Consumption | Row-level locking (`FOR UPDATE`) consumes OTP upon first verification. |
| **Superseding** | Active Invalidation | Requesting a new OTP immediately supersedes previous unconsumed codes. |
| **Session Isolation** | Pending State | Password verification does **not** grant authenticated session. |
| **Role Verification** | Strict 2-Role Guard | Only `hr` and `employee` roles can complete authentication. |
| **Fixation Defense** | Session Regeneration | `session_regenerate_id(true)` is executed upon successful OTP entry. |

---

## 7. Automated Testing Suite

Execute the PHPUnit suite:

```bash
# Run all unit, feature, and integration tests:
./vendor/bin/phpunit
```

All 53 automated tests execute and pass with 288 assertions, verifying:
- Email-based authentication and email normalization.
- Inactive and terminated accounts are rejected.
- Removal of demo accounts, sample passwords, and shortcuts.
- OTP hashing, expiration, attempt exhaustion, and single-use enforcement.
- Strict role boundaries (HR dashboard vs Employee self-service).
- Employee access to HR features is blocked.
- SMTP exception handling and credential masking.
