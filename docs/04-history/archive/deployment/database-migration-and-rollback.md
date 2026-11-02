# Database Migration & Rollback Guide

## Employee HRIS & Leave Management System

### 1. Database Architecture & Schema Integrity
The system database (`hris_db`) utilizes MySQL 8.x / MariaDB with the InnoDB storage engine, foreign key referential integrity constraints, and UTF-8 (`utf8mb4`) encoding.

#### Core Relational Tables
1. `users`: Authentication identities, password hashes, and RBAC roles (`admin`, `hr`, `employee`).
2. `departments`: Organizational business units with uniqueness constraints.
3. `positions`: Standardized job roles scoped to parent departments.
4. `employees`: Master employee demographic, organizational, and employment lifecycle records.
5. `leave_types`: Statutory and company leave policies (`Vacation Leave`, `Sick Leave`, etc.).
6. `leave_balances`: Annual employee leave quotas, usage, and remaining entitlements.
7. `leave_requests`: Formal employee leave applications, statuses (`Pending`, `Approved`, `Rejected`, `Cancelled`), and review audit fields.
8. `activity_logs`: Immutable security and operational audit trail records.
9. `notifications`: In-app system and workflow notifications.

---

### 2. Migration Principles
- **Non-Destructive Execution:** Migrations must add tables, columns, or non-conflicting indexes without dropping active data.
- **Transactional Migrations:** Multi-step schema adjustments must execute within transactions where supported.
- **Rollback Scripts Provided:** Every forward migration file must have a corresponding rollback counterpart.
- **No Automatic Reset:** Migrations should never execute `DROP DATABASE` or `TRUNCATE TABLE` in a production environment.

---

### 3. Step-by-Step Forward Migration

#### Step 1: Pre-Migration Backup
Always take a snapshot backup prior to applying schema migrations:
```bash
mysqldump -u hris_user -p --single-transaction --routines --triggers hris_db > backup_pre_migration_$(date +%Y%m%d_%H%M%S).sql
```

#### Step 2: Execute Schema Migration
Run the incremental migration script against the target database:
```bash
mysql -u hris_user -p -h 127.0.0.1 hris_db < database/migrations/migration_v1_to_v2.sql
```

#### Step 3: Verify Integrity
Confirm table presence, foreign keys, and row counts:
```bash
php -r 'require "core/bootstrap.php"; $pdo = Core\Database::getInstance()->getConnection(); print_r($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));'
```

---

### 4. Rollback Procedure
If a migration fails or causes unexpected regressions:

#### Step 1: Execute Corresponding Rollback Script
```bash
mysql -u hris_user -p -h 127.0.0.1 hris_db < database/migrations/rollback_v2_to_v1.sql
```

#### Step 2: Full Database Restoration (If Rollback Script Fails)
If schema corruption occurred, restore from the pre-migration snapshot:
```bash
mysql -u hris_user -p -h 127.0.0.1 hris_db < backup_pre_migration_YYYYMMDD_HHMMSS.sql
```

#### Step 3: Post-Rollback Verification
Re-run automated tests to confirm system consistency:
```bash
php scratch/test_rbac_suite.php
```
