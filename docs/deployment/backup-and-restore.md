# Database Backup & Disaster Recovery Guide

## Employee HRIS & Leave Management System

### 1. Backup Strategy Overview
A robust backup and restore routine ensures business continuity and protects against accidental data loss, ransomware, hardware degradation, or catastrophic human error.

#### Backup Objectives
- **Recovery Point Objective (RPO):** Maximum 1 hour of transaction data loss.
- **Recovery Time Objective (RTO):** System restored and verified within 30 minutes.
- **Backup Types:** Daily full logical backups (`mysqldump`) and binary logging (`binlog`) for point-in-time recovery (PITR).

---

### 2. Scheduled Automated Backup Procedure

#### Backup Command (mysqldump)
Run a transaction-consistent, non-locking backup using `--single-transaction`:
```bash
mysqldump -u hris_backup_user -p \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --databases hris_db | gzip > /var/backups/hris/hris_db_$(date +%Y%m%d_%H%M%S).sql.gz
```

#### Crontab Daily Schedule
Configure cron on the database server (`crontab -e`):
```cron
# Run daily database backup at 02:00 AM
0 2 * * * /usr/local/bin/backup_hris.sh >> /var/log/hris_backup.log 2>&1
```

#### Backup Retention Policy
- Daily backups retained for 14 days.
- Weekly backups retained for 8 weeks.
- Monthly backups retained for 12 months.
- Backups synced to an offsite secure storage bucket (e.g., S3/GCS) with restricted access and KMS encryption.

---

### 3. Step-by-Step Restoration Procedure

#### Step 1: Isolate the Environment
Put the web application into maintenance mode to prevent incoming writes:
```bash
# In production Apache virtual host or via temporary rewrite
touch /var/www/hris/public/maintenance.flag
```

#### Step 2: Unpack the Backup Archive
```bash
cd /var/backups/hris
gunzip -c hris_db_20261012_020000.sql.gz > restore_snapshot.sql
```

#### Step 3: Restore the Database
Import the snapshot into MySQL:
```bash
mysql -u hris_user -p -h 127.0.0.1 hris_db < restore_snapshot.sql
```

#### Step 4: Verify Database Health & Integrity
Check that all 9 core tables exist and row counts align with pre-incident benchmarks:
```bash
php -r 'require "core/bootstrap.php"; $pdo = Core\Database::getInstance()->getConnection(); foreach (["users","employees","departments","positions","leave_requests","leave_balances","activity_logs"] as $t) { echo "$t: " . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . PHP_EOL; }'
```

#### Step 5: Smoke Test & Resume Service
Run the automated test suites:
```bash
php scratch/test_rbac_suite.php
```
Remove the maintenance flag to restore live operations:
```bash
rm -f /var/www/hris/public/maintenance.flag
```
