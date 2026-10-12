# Environment Configuration Guide

## Employee HRIS & Leave Management System

### 1. Configuration Principles
- **No Committed Secrets:** Credentials, database passwords, session secrets, and sensitive tokens are strictly excluded from version control (`.gitignore`).
- **Explicit Environment Modes:** The application operates in two distinct modes:
  - `development`: Detailed error diagnostics, full stack traces, lenient local routing.
  - `production`: Error suppression for end-users, server-side audit error logging, hardened security headers, strict HTTPS session cookies.
- **Fail-Safe Startup:** In production, missing database connectivity or fatal configuration errors safely render `500.php` without leaking database connection strings or filesystem paths.

---

### 2. Configuration Key Reference

| Variable | Description | Development Default | Production Recommended |
| :--- | :--- | :--- | :--- |
| `APP_NAME` | Display name of the HRIS portal | `"Employee HRIS & Leave Management System"` | `"Enterprise HRIS Portal"` |
| `APP_ENV` | Application runtime environment | `development` | `production` |
| `BASE_URL` | Public entry URL path | `http://localhost/.../public` | `https://hris.company.com` |
| `TIMEZONE` | PHP timezone identifier | `Asia/Manila` | `Asia/Manila` (or regional) |
| `SESSION_LIFETIME` | Session expiration in seconds | `1800` (30 minutes) | `1800` |
| `DB_DRIVER` | PDO database driver | `mysql` | `mysql` |
| `DB_HOST` | Database server hostname / IP | `127.0.0.1` | `127.0.0.1` (or private VPC host) |
| `DB_PORT` | Database server TCP port | `3306` | `3306` |
| `DB_DATABASE` | Database schema name | `hris_db` | `hris_db_prod` |
| `DB_USERNAME` | Dedicated application user | `root` | `hris_app_user` (least privilege) |
| `DB_PASSWORD` | Database user password | `""` (empty in XAMPP) | High-entropy 32+ character secret |
| `DB_CHARSET` | Character set encoding | `utf8mb4` | `utf8mb4` |

---

### 3. Loading Mechanism
The application loads environment variables in `core/bootstrap.php`:
1. If a `.env` file exists in the workspace root, simple `KEY=VALUE` pairs are loaded into `$_ENV` and `getenv()`.
2. `config/config.php` and `config/database.php` read from `getenv()` with safe local fallbacks.
3. System environment variables (e.g. injected via Docker, systemd, or Apache `SetEnv`) take precedence over `.env` files.

---

### 4. Transitioning from Development to Production

#### In Development (Local XAMPP):
```ini
APP_ENV=development
BASE_URL="http://localhost/GitHub_Clone_File_Projects/HRIS-SYSTEM/public"
DB_USERNAME=root
DB_PASSWORD=
```
- Detailed stack trace screens enabled on uncaught exception.
- `display_errors = 1`.

#### In Production:
```ini
APP_ENV=production
BASE_URL="https://hris.example.com"
DB_USERNAME=hris_prod_user
DB_PASSWORD="[HIGH_ENTROPY_SECURE_PASSWORD]"
```
- Generic friendly `500.php` rendered on exception.
- Full stack traces and sensitive database parameters recorded solely to `/var/log/apache2/error.log` or PHP error log.
- `display_errors = 0`, `log_errors = 1`.
