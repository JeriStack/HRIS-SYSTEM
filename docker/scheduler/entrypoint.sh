#!/bin/bash
set -e

# =============================================================================
# NEXORA HR — Scheduler Container Entrypoint
# Exports runtime environment variables to /etc/environment so that cron jobs
# executed in isolated subshells inherit DB credentials, timezone, and config.
# =============================================================================

echo "[scheduler-entrypoint] Initializing NEXORA HR Cron Scheduler..."

# Export environment variables safely quoted (handles values containing spaces such as APP_NAME)
: > /etc/environment
while IFS='=' read -r name value; do
    case "$name" in
        no_proxy|NO_PROXY|_|SHLVL|PWD|OLDPWD) continue ;;
    esac
    if [[ "$name" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]]; then
        printf '%s="%s"\n' "$name" "${value//\"/\\\"}" >> /etc/environment
    fi
done < <(env)
chmod 0640 /etc/environment
chgrp www-data /etc/environment

# Wait for MySQL database dependency if DB_HOST is set
if [ -n "$DB_HOST" ]; then
    echo "[scheduler-entrypoint] Waiting for database connection at ${DB_HOST}:${DB_PORT:-3306}..."
    until mysqladmin ping -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-hris_user}" -p"${DB_PASSWORD:-hris_password}" --silent 2>/dev/null; do
        sleep 2
    done
    echo "[scheduler-entrypoint] Database connection established successfully."
fi

# Ensure runtime storage subdirectories exist with least-privilege www-data ownership (never 0777)
mkdir -p /var/www/html/storage/locks /var/www/html/storage/exports /var/www/html/storage/logs
chown -R www-data:www-data /var/www/html/storage/locks /var/www/html/storage/exports /var/www/html/storage/logs
chmod -R 0775 /var/www/html/storage/locks /var/www/html/storage/exports /var/www/html/storage/logs

# Ensure system crontab in /etc/cron.d has required root ownership and 0644 permissions
# (Debian cron automatically loads /etc/cron.d/hris-scheduler; do NOT run `crontab` on a 6-field file)
chown root:root /etc/cron.d/hris-scheduler
chmod 0644 /etc/cron.d/hris-scheduler

echo "[scheduler-entrypoint] Starting cron daemon in foreground..."
exec "$@"
