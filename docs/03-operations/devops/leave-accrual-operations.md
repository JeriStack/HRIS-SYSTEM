# HRIS-SYSTEM: Automated Leave Accrual & Annual Allocation Operations

**Document:** `docs/devops/leave-accrual-operations.md`  
**Tool:** `bin/accrue-leave.php`  
**Target:** Annual and Monthly Leave Quota Allocation  
**Status:** Production Standard  

---

## 1. Overview & Business Rules

Leave balances in the **Employee HRIS & Leave Management System** are tracked per employee, per leave category, per calendar year in the `leave_balances` table:

$$\text{Remaining Days} = \text{Allocated Days} - \text{Used Days}$$

To ensure workforce continuity and prevent manual administrative overhead at the beginning of each calendar year, the system provides a transaction-safe, idempotent CLI command: `bin/accrue-leave.php`.

### Invariants Enforced
1. **Idempotency:** Executing the accrual engine multiple times for the same year will **never** double-allocate or overwrite existing employee balances.
2. **Active Workforce Only:** Employees with `employment_status IN ('Inactive', 'Terminated')` are excluded.
3. **Active Leave Categories Only:** Deprecated or inactive leave types (`status = 'Inactive'`) are excluded.
4. **Audit Trail:** Every created or updated balance records an entry in `activity_logs` (`action = 'leave_balance_accrual'`).
5. **Database Transaction Safety:** All balance insertions for a run are enclosed in a single database transaction; unhandled exceptions trigger an automatic rollback.

---

## 2. CLI Command Reference

Execute the accrual script inside the application container or on the host:

```bash
# Inside Docker container
docker exec -it hris_app php bin/accrue-leave.php [OPTIONS]
```

### Supported Flags

| Flag | Description | Default |
| :--- | :--- | :--- |
| `--year=YYYY` | Calendar year for which balances are allocated. | Current calendar year (`date('Y')`). |
| `--dry-run` | Simulates calculation and displays planned actions without writing to MySQL. | Disabled. |
| `--force` | Updates existing balances to match default quotas (preserves used days). | Disabled. |
| `--help` | Displays command usage and available flags. | — |

### Example Invocations

```bash
# 1. Preview upcoming allocations without altering database
php bin/accrue-leave.php --year=2027 --dry-run

# 2. Execute annual allocation for 2027
php bin/accrue-leave.php --year=2027

# 3. Force quota synchronization
php bin/accrue-leave.php --year=2026 --force
```

---

## 3. Production Scheduling on Linux VPS

### Option A: Scheduled Cron Job (Recommended)

Add a cron job to the VPS host (`crontab -e`) to execute the annual accrual on January 1 at 00:05 AM UTC:

```cron
# Run annual leave accrual for the new year on Jan 1st at 00:05 UTC
5 0 1 1 * docker exec hris_app php bin/accrue-leave.php >> /var/log/hris-leave-accrual.log 2>&1
```

### Option B: Systemd Timer Service

For enterprise environments using systemd timers:

**1. Define Service (`/etc/systemd/system/hris-leave-accrual.service`):**
```ini
[Unit]
Description=HRIS Automated Leave Accrual Engine
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
User=deployer
ExecStart=/usr/bin/docker exec hris_app php bin/accrue-leave.php
StandardOutput=journal
StandardError=journal
```

**2. Define Timer (`/etc/systemd/system/hris-leave-accrual.timer`):**
```ini
[Unit]
Description=Run HRIS Leave Accrual on January 1st

[Timer]
OnCalendar=*-01-01 00:05:00 UTC
Persistent=true

[Install]
WantedBy=timers.target
```

**3. Enable and Start the Timer:**
```bash
systemctl daemon-reload
systemctl enable --now hris-leave-accrual.timer
systemctl list-timers --all | grep hris
```

