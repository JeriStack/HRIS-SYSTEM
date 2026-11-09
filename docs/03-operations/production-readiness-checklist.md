# NEXORA HR — Production Readiness Checklist & Security Verification Matrix

**Project:** NEXORA HR — Human Resource Information System  
**Tagline:** People. Processes. Progress.  
**Phase:** 16.4 — Production Readiness Evaluation  
**Audit Date:** 2026-11-09

---

## 1. Production Readiness Checklist (18-Point Evaluation)

| # | Evaluation Criterion | Status | Implementation Evidence & Verification Path | Notes / Environment Requirement |
| :---: | :--- | :---: | :--- | :--- |
| **1** | **Debug mode disabled in production** | **PASS** | `core/bootstrap.php`, `public/index.php` | When `APP_ENV=production`, `display_errors` is disabled (`0`) and generic error views (`app/views/errors/500.php`) are rendered without stack traces. |
| **2** | **Secure environment configuration** | **PASS** | `.env.example`, `core/bootstrap.php`, `.gitignore` | `.env` is git-ignored; `vlucas/phpdotenv` loads environment variables with safe fallback defaults. |
| **3** | **No committed secrets or credentials** | **PASS** | `.gitignore`, `.env.example`, `git status` | Verified `.env` is excluded from Git tracking; `.env.example` contains only placeholder templates. |
| **4** | **HTTPS / TLS configuration** | **PASS** | `docs/03-operations/deployment/DEPLOYMENT.md`, `core/functions.php` (`is_https_request()`) | Nginx TLS 1.2/1.3 termination configuration documented; `is_https_request()` honors `HTTPS` and trusted `X-Forwarded-Proto` headers. |
| **5** | **Secure session cookies** | **PASS** | `core/Session.php` | Enforces `httponly=true`, `samesite=Lax`, `use_strict_mode=1`, and automatically enables `secure=true` when served over HTTPS or `SESSION_COOKIE_SECURE=true`. |
| **6** | **CSRF protection on state-changing routes** | **PASS** | `core/CsrfMiddleware.php`, `public/index.php` | Synchronized per-session CSRF token validated via timing-safe `hash_equals()` on all `POST`, `PUT`, `DELETE`, and `PATCH` requests. |
| **7** | **Rate limiting on authentication & OTP** | **PASS** | `app/controllers/AuthController.php`, `app/models/EmailOtp.php` | Enforces per-account (`LOGIN_RATE_LIMIT_MAX_ACCOUNT=5`) and per-IP (`LOGIN_RATE_LIMIT_MAX_IP=15`) rate limits within a 15-minute window, plus max 5 OTP attempts per code. |
| **8** | **Authentication & MFA safeguards** | **PASS** | `app/controllers/AuthController.php`, `app/models/EmailOtp.php`, `app/services/TotpService.php`, `app/services/StepUpAuthService.php` | Bcrypt password hashing, HMAC-SHA256 peppered 6-digit Email OTP, AES-256-GCM encrypted TOTP, Step-Up Authentication (15-min window), and continuous `auth_fingerprint` verification. |
| **9** | **Server-side 3-Role RBAC enforcement** | **PASS** | `core/RoleMiddleware.php`, `core/PermissionMiddleware.php`, `core/AuthorizationService.php`, `core/PermissionRegistry.php` | Fail-closed server-side role and capability enforcement across all Web routes, REST API v1 endpoints, and WebSocket channels (`ChannelPolicy`). |
| **10** | **Database backup & disaster recovery** | **PASS** | `docs/03-operations/devops/backup-and-recovery.md`, `docs/03-operations/deployment/DEPLOYMENT.md` | Documented `mysqldump --single-transaction` automated daily backups, 30-day retention policy, and persistent Docker volume (`hris_db_data`) safeguards. |
| **11** | **Database migration safety** | **PASS** | `bin/migrate.php`, `core/MigrationRunner.php`, `tests/Feature/MigrationRunnerTest.php` | Tracks applied migrations in `migrations` table; `MigrationRunner` halts and refuses to mark a migration applied if any statement fails; `tests/bootstrap.php` refuses to run destructive tests outside `hris_test`. |
| **12** | **Logging, audit trail & monitoring** | **PASS** | `core/AuditService.php`, `docker/prometheus/`, `docker/grafana/`, `docker-compose.yml` | Tamper-evident SHA-256 hash-chained `activity_logs` with secret redaction, plus Prometheus (`:9090`) and Grafana (`:3100`) scraping `nginx-exporter` and `mysqld-exporter`. |
| **13** | **Queue worker health & dead-letter handling** | **PASS** | `bin/queue-worker.php`, `app/Queue/QueueWorker.php`, `tests/Feature/QueueWorkerTest.php` | Atomic job reservation leases (`reserved_at`, `reserved_by`), exponential backoff retries, graceful `SIGTERM`/`SIGINT` shutdown, and `failed_jobs` dead-letter archival. |
| **14** | **Scheduler health & idempotency** | **PASS** | `bin/console`, `app/Console/Kernel.php`, `docker/scheduler/Dockerfile`, `tests/Feature/CronSchedulerTest.php` | Dedicated `hris_scheduler` container executing idempotent monthly leave accrual, year-end carry-over, upcoming leave reminders, expired token pruning, and audit archival. |
| **15** | **Centralized error handling** | **PASS** | `public/index.php`, `core/functions.php`, `app/views/errors/` | Global exception handler catches uncaught `Throwable` instances, logs diagnostic details server-side, and returns clean JSON or branded HTML error views (`403.php`, `404.php`, `500.php`). |
| **16** | **File & directory permissions** | **PASS** | `Dockerfile`, `docker-compose.yml` | Web document root isolated to `public/`; background daemons (`realtime`, `worker`) execute as non-root `www-data` user; sensitive directories (`core/`, `app/`, `config/`, `database/`) are outside `public/`. |
| **17** | **Dependency & CI security** | **PASS** | `composer.json`, `.github/workflows/ci.yml` | Locked dependencies validated in GitHub Actions CI (`actionlint`, `php -l`, `check-phpcs-baseline.php`, `phpstan`, `phpunit`, `tflint`, `hadolint`). |
| **18** | **Last active Admin & HR lockout protection** | **PASS** | `app/models/User.php` (`countActiveAdmins`, `countActiveHr`), `app/controllers/UserController.php`, `core/AuthorizationService.php` | Server-side guards block deactivating or demoting the last active Administrator or HR Officer, block self-demotion/self-deactivation, and enforce immutable `admin` role permissions. |

---

## 2. Pre-Production Go-Live Operator Checklist

Before exposing a production instance to external traffic, the deployment operator must complete the following environment-specific steps:
1. Set `APP_ENV=production` and `SESSION_COOKIE_SECURE=true` in the production `.env`.
2. Generate and configure strong, unique secrets for `DB_PASSWORD`, `OTP_PEPPER_SECRET` (`bin2hex(random_bytes(32))`), and `GRAFANA_ADMIN_PASSWORD`.
3. Provision a valid TLS certificate (e.g., Let's Encrypt via Certbot) on the edge Nginx proxy and set `WS_PUBLIC_URL=wss://<your-domain>/ws` and `WS_ALLOWED_ORIGINS=https://<your-domain>`.
4. Provision the initial Administrator account via `php bin/create_admin_user.php` and verify 2FA Email OTP delivery over the production SMTP relay.

