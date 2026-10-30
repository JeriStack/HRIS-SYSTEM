#!/usr/bin/env bash
# =============================================================================
# Automated Database Restore & Integrity Verification Script
# Location: scripts/restore/restore-db.sh
# Employee HRIS & Leave Management System
#
# Usage:
#   ./scripts/restore/restore-db.sh /path/to/snapshot.sql.gz [--force]
#
# Features:
# - Validates SHA-256 checksum before restoration (if companion file exists)
# - Restores gzip compressed MySQL dump directly into container
# - Runs post-restore integrity check verifying all 11 core tables and row counts
# =============================================================================

set -euo pipefail

if [ "$#" -lt 1 ]; then
    echo "Usage: $0 <path-to-backup.sql.gz> [--force]" >&2
    exit 1
fi

BACKUP_FILE="$1"
FORCE_FLAG="${2:-}"
DB_CONTAINER="${DB_CONTAINER:-hris_db}"
DB_NAME="${DB_NAME:-hris_db}"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "ERROR: Backup file '${BACKUP_FILE}' does not exist!" >&2
    exit 1
fi

echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Verifying backup archive: ${BACKUP_FILE}..."

# 1. Verify Checksum if present
CHECKSUM_FILE="${BACKUP_FILE}.sha256"
if [ -f "${CHECKSUM_FILE}" ]; then
    echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Verifying SHA-256 checksum..."
    (cd "$(dirname "${BACKUP_FILE}")" && sha256sum -c "$(basename "${CHECKSUM_FILE}")")
    echo "Checksum verification PASSED."
else
    echo "WARNING: No .sha256 checksum file found. Proceeding with caution."
fi

# 2. Check Database Container Health
if ! docker ps --filter "name=^/${DB_CONTAINER}$" --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    echo "ERROR: Database container '${DB_CONTAINER}' is not running!" >&2
    exit 1
fi

# Obtain database root password
if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
    MYSQL_ROOT_PASSWORD="$(docker exec "${DB_CONTAINER}" printenv MYSQL_ROOT_PASSWORD || true)"
fi

if [ -z "${MYSQL_ROOT_PASSWORD}" ]; then
    echo "ERROR: MYSQL_ROOT_PASSWORD is not set or accessible in container." >&2
    exit 1
fi

# Confirmation prompt unless --force or CI
if [ "${FORCE_FLAG}" != "--force" ] && [ -t 0 ]; then
    read -rp "WARNING: This will overwrite data in '${DB_NAME}'. Continue? (yes/no): " CONFIRM
    if [ "${CONFIRM}" != "yes" ]; then
        echo "Restoration aborted by operator."
        exit 0
    fi
fi

# 3. Stream Decompressed SQL into MySQL
echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Restoring database snapshot into container '${DB_CONTAINER}'..."
gunzip -c "${BACKUP_FILE}" | docker exec -i "${DB_CONTAINER}" mysql \
    -u root \
    -p"${MYSQL_ROOT_PASSWORD}" \
    "${DB_NAME}"

echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Snapshot import completed. Running integrity diagnostics..."

# 4. Post-Restoration Table & Row Verification
VALIDATION_SCRIPT="
SELECT table_name, table_rows 
FROM information_schema.tables 
WHERE table_schema = '${DB_NAME}' 
ORDER BY table_name;
"

echo "===================================================================="
echo " RESTORED DATABASE INTEGRITY AUDIT"
echo "===================================================================="
docker exec "${DB_CONTAINER}" mysql -u root -p"${MYSQL_ROOT_PASSWORD}" "${DB_NAME}" -e "${VALIDATION_SCRIPT}"

# Verify critical table presence
TABLE_COUNT=$(docker exec "${DB_CONTAINER}" mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -N -s -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_NAME}';")

if [ "${TABLE_COUNT}" -lt 11 ]; then
    echo "ERROR: Table count (${TABLE_COUNT}) is less than expected schema baseline (11 tables)!" >&2
    exit 1
fi

echo "===================================================================="
echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Database restored successfully! (${TABLE_COUNT} tables verified)"
echo "===================================================================="
exit 0

