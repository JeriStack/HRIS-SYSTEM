# NEXORA HR — Database Architecture & Schema Specification

**Project:** NEXORA HR  
**Tagline:** People. Processes. Progress.  
**Version:** 3.1  
**Status:** Authoritative Database Reference  
**Date:** November 2026  
**Document Owner:** Database Administrator & Backend Architecture Team  

---

## 1. Architectural Overview & Configuration

NEXORA HR utilizes **MySQL 8.0 Community Server** configured with the **InnoDB** storage engine as its single source of truth. The database is architected for strict ACID transactional compliance, row-level locking concurrency control, high referential integrity, and complete isolation between production and testing environments.

### Core Configuration Parameters
* **Storage Engine:** `InnoDB` strictly enforced across all 13 tables.
* **Character Set & Collation:** `utf8mb4` with `utf8mb4_unicode_ci` collation supporting full Unicode text and international employee names.
* **Transaction Isolation Level:** `READ COMMITTED` or `REPEATABLE READ` (default) with explicit pessimistic locks (`SELECT ... FOR UPDATE`) for critical balance transactions.
* **Timezone Synchronization:** UTC normalized timestamps with application-level display timezone conversion.

---

## 2. Authoritative 13-Table Schema Catalog

### 2.1 Organizational & Workforce Tables

#### 1. `departments`
Stores organizational department units.
```sql
CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_departments_name` UNIQUE (`department_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 2. `positions`
Stores job positions and organizational titles.
```sql
CREATE TABLE `positions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `position_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_positions_name` UNIQUE (`position_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3. `employees`
Core workforce registry detailing personal, organizational, and employment status attributes.
```sql
CREATE TABLE `employees` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_no` VARCHAR(30) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `middle_name` VARCHAR(50) NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(25) NULL,
    `address` TEXT NULL,
    `birth_date` DATE NOT NULL,
    `gender` ENUM('Male', 'Female', 'Other') NOT NULL,
    `department_id` INT UNSIGNED NOT NULL,
    `position_id` INT UNSIGNED NOT NULL,
    `date_hired` DATE NOT NULL,
    `employment_status` ENUM('Active', 'Inactive', 'Terminated') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_employees_emp_no` UNIQUE (`employee_no`),
    CONSTRAINT `uq_employees_email` UNIQUE (`email`),
    CONSTRAINT `fk_employees_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_employees_position` FOREIGN KEY (`position_id`) 
        REFERENCES `positions` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_employees_status` (`employment_status`),
    INDEX `idx_employees_dept` (`department_id`),
    INDEX `idx_employees_pos` (`position_id`),
    INDEX `idx_employees_name` (`last_name`, `first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 4. `users`
System user credentials, authentication attributes, and application roles.
```sql
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('hr', 'employee') NOT NULL DEFAULT 'employee',
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_users_username` UNIQUE (`username`),
    CONSTRAINT `uq_users_email` UNIQUE (`email`),
    CONSTRAINT `uq_users_employee` UNIQUE (`employee_id`),
    CONSTRAINT `fk_users_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.2 Leave Management Engine Tables

#### 5. `leave_types`
Catalog of system leave categories and annual base allocations.
```sql
CREATE TABLE `leave_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `leave_name` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `default_days` INT UNSIGNED NOT NULL DEFAULT 0,
    `requires_attachment` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_leave_types_name` UNIQUE (`leave_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 6. `leave_balances`
Annual quota tracking per employee, leave type, and calendar year.
```sql
CREATE TABLE `leave_balances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `year` SMALLINT UNSIGNED NOT NULL,
    `allocated_days` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `used_days` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `remaining_days` DECIMAL(4,1) NOT NULL DEFAULT 0.0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `uq_balances_emp_type_year` UNIQUE (`employee_id`, `leave_type_id`, `year`),
    CONSTRAINT `fk_balances_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_balances_leave_type` FOREIGN KEY (`leave_type_id`) 
        REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_balances_lookup` (`employee_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 7. `leave_requests`
Transactional leave applications submitted by employees.
```sql
CREATE TABLE `leave_requests` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `leave_type_id` INT UNSIGNED NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `number_of_days` DECIMAL(4,1) NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `approved_by` INT UNSIGNED NULL,
    `approved_at` DATETIME NULL,
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_requests_employee` FOREIGN KEY (`employee_id`) 
        REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_requests_leave_type` FOREIGN KEY (`leave_type_id`) 
        REFERENCES `leave_types` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_requests_approver` FOREIGN KEY (`approved_by`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_requests_status` (`status`),
    INDEX `idx_requests_emp_dates` (`employee_id`, `start_date`, `end_date`),
    INDEX `idx_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### 2.3 Authentication, Security & Real-Time Tables

#### 8. `activity_logs`
Tamper-evident audit trail of all operational and security actions.
```sql
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` VARCHAR(50) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_logs_user_action` (`user_id`, `action`),
    INDEX `idx_logs_entity` (`entity_type`, `entity_id`),
    INDEX `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 9. `notifications`
In-app notification messages delivered to specific users.
```sql
CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `type` VARCHAR(30) NOT NULL DEFAULT 'info',
    `entity_type` VARCHAR(50) NULL,
    `entity_id` VARCHAR(50) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_notifications_user_read` (`user_id`, `is_read`),
    INDEX `idx_notifications_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 10. `email_otps`
Hashed one-time passwords for multi-factor authentication.
```sql
CREATE TABLE `email_otps` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_email_otps_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_email_otps_user_expires` (`user_id`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 11. `realtime_tickets`
Single-use authentication tickets for the Ratchet WebSocket daemon.
```sql
CREATE TABLE `realtime_tickets` (
    `ticket` VARCHAR(64) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `role` VARCHAR(30) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_realtime_tickets_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_realtime_tickets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 12. `outbox_events`
Asynchronous Transactional Outbox event staging table.
```sql
CREATE TABLE `outbox_events` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `event_type` VARCHAR(100) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `status` ENUM('PENDING', 'PROCESSING', 'PUBLISHED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `processed_at` DATETIME NULL,
    INDEX `idx_outbox_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 13. `user_totp`
RFC 6238 TOTP authenticator secret credentials.
```sql
CREATE TABLE `user_totp` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `secret` VARCHAR(255) NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_user_totp_user` UNIQUE (`user_id`),
    CONSTRAINT `fk_user_totp_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Concurrency Control & Row-Level Locking

Leave balance mutations execute inside atomic transactions using **pessimistic row-level locking**:

```php
$pdo->beginTransaction();
try {
    // 1. Lock leave request
    $stmtReq = $pdo->prepare("SELECT * FROM leave_requests WHERE id = :id FOR UPDATE");
    $stmtReq->execute(['id' => $requestId]);
    $request = $stmtReq->fetch(PDO::FETCH_ASSOC);

    // Validate request is still 'Pending'
    if ($request['status'] !== 'Pending') {
        throw new RuntimeException("Request has already been processed.");
    }

    // 2. Lock employee leave balance record
    $stmtBal = $pdo->prepare("
        SELECT * FROM leave_balances 
        WHERE employee_id = :emp_id AND leave_type_id = :type_id AND year = :year 
        FOR UPDATE
    ");
    $stmtBal->execute([
        'emp_id'  => $request['employee_id'],
        'type_id' => $request['leave_type_id'],
        'year'    => (int)date('Y', strtotime($request['start_date']))
    ]);
    $balance = $stmtBal->fetch(PDO::FETCH_ASSOC);

    // 3. Verify sufficient quota
    if ((float)$balance['remaining_days'] < (float)$request['number_of_days']) {
        throw new RuntimeException("Insufficient leave balance.");
    }

    // 4. Mutate status & balance atomically
    $pdo->prepare("UPDATE leave_requests SET status = 'Approved', approved_by = :approver, approved_at = NOW() WHERE id = :id")
        ->execute(['approver' => $currentUserId, 'id' => $requestId]);

    $pdo->prepare("UPDATE leave_balances SET used_days = used_days + :days, remaining_days = remaining_days - :days WHERE id = :id")
        ->execute(['days' => $request['number_of_days'], 'id' => $balance['id']]);

    // 5. Commit & release all locks
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

## 4. Migration Management & CLI Runner

Database migrations are tracked in the `migrations` table:
```sql
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

To run pending migrations:
```bash
php bin/migrate.php
```

All migrations must adhere to the following naming pattern:
`database/migrations/YYYY_MM_DD_descriptive_action_name.sql`

