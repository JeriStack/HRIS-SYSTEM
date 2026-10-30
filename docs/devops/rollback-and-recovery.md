# Rollback, Disaster Recovery, and Backup Procedures
## HRIS-SYSTEM — Local Infrastructure and Database Protection

---

## 1. Core Data Protection Principles

To guarantee zero accidental data loss across development and deployment cycles:

1. **NEVER Run Destructive Compose Commands:**
   - **PROHIBITED:** `docker compose down -v` (The `-v` flag permanently removes persistent Docker volumes, wiping all employee records, leave requests, and audit logs).
   - **SAFE ALTERNATIVE:** `docker compose down` or `docker compose stop` (Preserves volumes, networks, and database contents).
2. **NEVER Use `terraform destroy` for Routine Troubleshooting:**
   - `terraform destroy` tears down provisioned infrastructure. Only use it when deliberately cleaning up isolated learning resources or decommissioned staging tiers.
3. **Backup Before High-Risk Upgrades:**
   - Always take a cold or logical database snapshot before database migrations, schema alters, or container upgrades.

---

## 2. MySQL Database Backup Procedures

### 2.1 Logical Backup via `mysqldump` (Recommended)
Generate a full SQL export including schemas, tables, views, stored procedures, and data:

```powershell
# Create local backup directory
New-Item -ItemType Directory -Force -Path storage\backups

# Export full database dump
docker exec hris_db mysqldump -u root -prootpassword --routines --triggers --single-transaction hris_db > storage\backups\hris_db_backup_$(Get-Date -Format "yyyyMMdd_HHmmss").sql
```

### 2.2 Compressed Backup for Archiving
```powershell
docker exec hris_db mysqldump -u root -prootpassword --single-transaction hris_db | gzip > storage\backups\hris_db_backup_latest.sql.gz
```

---

## 3. Database Restoration Procedures

To restore the HRIS database from a verified SQL dump:

### Step 1: Verify MySQL Container is Healthy
```powershell
docker inspect --format='{{json .State.Health.Status}}' hris_db
```

### Step 2: Stream SQL File Into MySQL Container
```powershell
# Restore from uncompressed SQL dump
Get-Content storage\backups\hris_db_backup_latest.sql | docker exec -i hris_db mysql -u root -prootpassword hris_db
```

### Step 3: Verify Integrity Post-Restoration
```powershell
docker exec hris_app php -r "$pdo = new PDO('mysql:host=db;port=3306;dbname=hris_db', 'hris_user', 'hris_password'); echo 'Employees: ' . $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn() . PHP_EOL;"
```

---

## 4. Terraform Rollback and Recovery

If an erroneous `terraform apply` introduces unwanted changes or configuration drift:

### Step 1: Inspect Current Terraform State
```powershell
cd terraform\environments\dev
terraform show
```

### Step 2: Run a Non-Destructive Plan
```powershell
terraform plan
```
Identify which resources are planned for addition (`+`), modification (`~`), or replacement (`-/+`).

### Step 3: Revert Configuration Changes via Git
```powershell
git checkout HEAD -- main.tf variables.tf
```

### Step 4: Re-apply Known Good State
```powershell
terraform apply -auto-approve
```

---

## 5. Graceful Shutdown & Disaster Recovery Checklist

### Safe End-of-Day Shutdown
To shut down your workstation without losing container state:
```powershell
# Gracefully stop running containers (preserves container IDs, networks, and data volumes)
docker compose stop
```

### Clean System Startup
To resume development the next day:
```powershell
docker compose start
```

### Emergency Service Recovery (Single Service Reset)
If an individual container hangs or encounters an unrecoverable state:
```powershell
# Example: Restart Nginx gateway
docker compose restart nginx

# Example: Recreate PHP Application container without touching database
docker compose up -d --no-deps --build app
```

