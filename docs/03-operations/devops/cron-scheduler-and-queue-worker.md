# NEXORA HR — Cron Scheduler & Queue Worker Operational Guide

**Authoritative Documentation (HRIS-53)**  
**Architecture Approach:** Option A — Dedicated Docker Scheduler Container  
**Queue Backend:** MySQL-backed Job Queue with Dead-Letter Handling  
**Environment:** PHP 8.5 + MySQL 8.0 + Docker Compose

---

## 1. System Architecture

NEXORA HR operates two independent background execution containers:

1. **`hris_scheduler` (Cron Scheduler Container):** Dedicated container running the system cron daemon in the foreground. Executes periodic maintenance tasks through the PHP CLI without processing HTTP requests.
2. **`hris_worker` (Queue Worker Container):** Long-lived PHP CLI daemon retrieving and executing asynchronous background jobs (emails, reminders, report exports) from MySQL using atomic locking.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        NEXORA HR Architecture                          │
│                                                                        │
│   ┌────────────────────┐          ┌────────────────────┐               │
│   │   Web Application  │          │   Cron Scheduler   │               │
│   │   (Apache / HTTP)  │          │   (Option A Cron)  │               │
│   └─────────┬──────────┘          └─────────┬──────────┘               │
│             │                               │                          │
│             │ push()                        │ schedule:run             │
│             ▼                               ▼                          │
│   ┌────────────────────┐          ┌────────────────────┐               │
│   │ queue_jobs (MySQL) │          │ Tasks / Locks      │               │
│   │ (InnoDB SKIP LOCKED│          │ (flock in storage) │               │
│   └─────────▲──────────┘          └────────────────────┘               │
│             │                                                          │
│             │ claimPending()                                           │
│             ▼                                                          │
│   ┌────────────────────┐                                               │
│   │    Queue Worker    │─────────► Registered Handlers                 │
│   │  (PHP CLI Daemon)  │           (Email, Notifications, Reminders)   │
│   └────────────────────┘                                               │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Docker Services Configuration

### 2.1 Scheduler Service (`hris_scheduler`)

Defined in `docker-compose.yml` and `docker-compose.prod.yml`:
* **Image Context:** Built from [`docker/scheduler/Dockerfile`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docker/scheduler/Dockerfile).
* **Crontab:** Mounted from [`docker/scheduler/crontab`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docker/scheduler/crontab).
* **Entrypoint:** [`docker/scheduler/entrypoint.sh`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/docker/scheduler/entrypoint.sh) exports environment variables into `/etc/environment` and waits for database health before launching `cron -f -L 15`.
* **Logging:** Task output is redirected to `/proc/1/fd/1`, making it immediately accessible via `docker logs hris_scheduler`.

### 2.2 Worker Service (`hris_worker`)

* **Image:** Reuses `hris-system-app:latest` (built by `app`).
* **Command:** `php bin/queue-worker.php --sleep=2 --lease=300 --max-runtime=3600`
* **User:** `www-data` (unprivileged execution).
* **Graceful Termination:** Configured with `stop_grace_period: 15s`. Handles `SIGTERM` and `SIGINT` via POSIX signals, allowing active jobs to finish before terminating.

---

## 3. Scheduled Tasks (Cron)

Tasks are registered in [`App\Console\Kernel`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/Console/Kernel.php) and executed via `php bin/console schedule:run`:

| Task Name | Cron Schedule | Script / Handler | Purpose |
| :--- | :--- | :--- | :--- |
| `cleanup-sessions` | `*/15 * * * *` | `scripts/scheduled/cleanup-sessions.php` | Purges expired OTPs (>1h), stale tickets (>10m), and expired session files. |
| `process-notifications`| `* * * * *` | `scripts/scheduled/process-notifications.php` | Claims and dispatches pending transactional outbox events and queued notification jobs. |
| `process-announcements`| `* * * * *` | `scripts/scheduled/process-announcements.php` | Publishes due `Scheduled` announcements (`scheduled_publish_at <= NOW()`) and expires past-due `Published` announcements (`expires_at <= NOW()`). |
| `send-leave-reminders` | `0 8 * * *` | `scripts/scheduled/send-leave-reminders.php` | Sends automated notifications for approved leaves commencing tomorrow. |
| `cleanup-temp-files` | `0 2 * * *` | `scripts/scheduled/cleanup-temp-files.php` | Removes CSV export files older than 24 hours from `storage/exports/`. |

### Overlap Protection
All scheduled tasks utilize [`App\Console\TaskLock`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/Console/TaskLock.php), which acquires a non-blocking exclusive file lock (`flock(LOCK_EX | LOCK_NB)`) in `storage/locks/`. If a previous invocation is still active, subsequent runs are safely skipped without process pileup.

---

## 4. MySQL-Backed Job Queue

### 4.1 Database Schema
Created via [`database/migrations/2026_11_08_create_queue_tables.sql`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/database/migrations/2026_11_08_create_queue_tables.sql):

* **`queue_jobs`**: Stores pending, reserved, and completed asynchronous jobs:
  - `id` (BIGINT UNSIGNED)
  - `queue` (VARCHAR(64), default: `'default'`)
  - `job_type` (VARCHAR(128))
  - `payload` (JSON, recursively scrubbed of sensitive credentials)
  - `status` (`pending`, `reserved`, `completed`, `failed`)
  - `attempts` (INT UNSIGNED)
  - `max_attempts` (INT UNSIGNED, default: 3)
  - `available_at` (DATETIME)
  - `reserved_at` (DATETIME)
  - `reserved_by` (VARCHAR(128))
  - `last_error` (TEXT)

* **`failed_jobs`**: Dead-letter storage for forensic analysis:
  - `job_id`, `queue`, `job_type`, `payload`, `attempts`, `exception`, `failed_at`.

### 4.2 Concurrency and Atomic Claiming
`App\Queue\QueueService::claimPending()` executes:
```sql
SELECT id, queue, job_type, payload, attempts, max_attempts
FROM queue_jobs
WHERE queue = :queue
  AND status = 'pending'
  AND available_at <= NOW()
ORDER BY id ASC
LIMIT :lim
FOR UPDATE SKIP LOCKED;
```
Inside a short transaction, claimed rows are updated to `status = 'reserved'`, `reserved_at = NOW()`, `reserved_by = :workerId`, and immediately committed. This guarantees that multiple worker containers never process the same job simultaneously.

### 4.3 Abandoned Reservation Recovery
If a worker container crashes or restarts while processing a job, the reservation expires after the configured lease period (default: 300 seconds). The worker's reaper periodically executes:
```sql
UPDATE queue_jobs
SET status = 'pending', reserved_at = NULL, reserved_by = NULL
WHERE status = 'reserved' AND reserved_at <= DATE_SUB(NOW(), INTERVAL 300 SECOND);
```

### 4.4 Exponential Backoff Retries
When a job throws an exception:
* If `attempts < max_attempts`: The job is rescheduled with exponential backoff:
  $$\text{delay} = \min(3600, 2^{\text{attempts}} \times 5 + \text{jitter})$$
* If `attempts >= max_attempts`: The job transitions to `failed` and is persisted into `failed_jobs`.

---

## 5. Registered Job Handlers

All executable handlers are registered in [`App\Queue\JobRegistry`](file:///c:/xampp/htdocs/GitHub_Clone_File_Projects/HRIS-SYSTEM/app/Queue/JobRegistry.php). Executing arbitrary class names or unverified PHP code is rejected:

1. `send_email` (`App\Queue\Handlers\SendEmailHandler`): Delivers HTML emails asynchronously via `MailService`.
2. `send_notification` (`App\Queue\Handlers\SendNotificationHandler`): Writes persistent in-app notifications with event-level deduplication.
3. `leave_reminder` (`App\Queue\Handlers\LeaveReminderHandler`): Identifies tomorrow's approved leaves and generates reminder alerts.
4. `report_export` (`App\Queue\Handlers\ReportExportHandler`): Generates and writes CSV export files in the background.
5. `outbox_relay` (`App\Queue\Handlers\OutboxRelayHandler`): Bridges transactional outbox events to domain event handlers.
6. `distribute_announcement_notifications` (`App\Queue\Handlers\DistributeAnnouncementNotificationsHandler`): Fan-out distribution of targeted in-app and WebSocket notifications (`announcement.published` / `announcement.urgent`) with idempotent `event_key = announcement_published_{id}_user_{uid}` deduplication and disabled bulk external email.

---

## 6. Critical Leave-Management Rule

Leave request approval, rejection, and quota calculations remain **100% synchronous, ACID-compliant database transactions**. Background jobs are dispatched *after* or *asynchronously with* business transactions. A failed email delivery or notification will **never** roll back an already committed leave transaction.

---

## 7. Practical CLI Commands

### Starting Containers (Docker Compose)
```bash
# Start full stack including scheduler and worker
docker compose up -d

# Start only scheduler and worker
docker compose up -d scheduler worker

# View live scheduler logs
docker compose logs -f scheduler

# View live queue worker logs
docker compose logs -f worker
```

### Console Management Commands
```bash
# List all registered scheduled tasks
php bin/console schedule:list

# Run due scheduled tasks manually
php bin/console schedule:run

# Inspect real-time queue health and metrics
php bin/console queue:status

# Start a local queue worker (daemon mode)
php bin/console queue:work

# Process pending jobs once and exit
php bin/console queue:work --once

# Retry a failed dead-letter job
php bin/console queue:retry 12
```

