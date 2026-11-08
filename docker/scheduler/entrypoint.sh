#!/bin/bash
set -e

# =============================================================================
# NEXORA HR — Scheduler Container Entrypoint
# Exports runtime environment variables to /etc/environment so that cron jobs
# executed in isolated subshells inherit DB credentials, timezone, and configuration.
# =============================================================================

echo "[scheduler-entrypoint] Initializing NEXORA HR Cron Scheduler..."

# Dump current environment variables (excluding sensitive interactive tokens) to /etc/environment
printenv | grep -v "no_proxy" > /etc/environment || true

# Wait for MySQL database dependency if DB_HOST is set
if [ -n "$DB_HOST" ]; then
    echo "[scheduler-entrypoint] Waiting for database connection at ${DB_HOST}:${DB_PORT:-3306}..."
    until mysqladmin ping -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-hris_user}" -p"${DB_PASSWORD:-hris_password}" --silent 2>/dev/null; do
        sleep 2
    done
    echo "[scheduler-entrypoint] Database connection established successfully."
fi

# Ensure storage lock directory exists and has proper permissions
mkdir -p /var/www/html/storage/locks /var/www/html/storage/exports
chmod -R 0777 /var/www/html/storage

# Install crontab
crontab /etc/cron.d/hris-scheduler

echo "[scheduler-entrypoint] Starting cron daemon in foreground..."
exec "$@"

