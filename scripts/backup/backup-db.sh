#!/usr/bin/env bash
# =============================================================================
# Automated Database Backup Script (scripts/backup/backup-db.sh)
# Employee HRIS & Leave Management System
#
# Features:
# - Transaction-consistent logical dump using mysqldump --single-transaction
# - Gzip compression on-the-fly
# - SHA-256 checksum generation for integrity verification
# - Prunes backups older than configured retention period (default: 14 days)
# - Exits with non-zero on any failure
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

BACKUP_DIR="${BACKUP_DIR:-${PROJECT_ROOT}/backups}"
DB_CONTAINER="${DB_CONTAINER:-hris_db}"
DB_NAME="${DB_NAME:-hris_db}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +"%Y%m%d_%H%M%S")"
BACKUP_FILE="${BACKUP_DIR}/hris_db_${TIMESTAMP}.sql.gz"
CHECKSUM_FILE="${BACKUP_FILE}.sha256"

# Ensure backup directory exists with restricted permissions
mkdir -p "${BACKUP_DIR}"
chmod 700 "${BACKUP_DIR}" 2>/dev/null || true

echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Starting HRIS database backup..."

# Verify container is running
if ! docker ps --filter "name=^/${DB_CONTAINER}$" --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    echo "ERROR: Target database container '${DB_CONTAINER}' is not running!" >&2
    exit 1
fi

# Obtain database root password from environment or container inspection
if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
    MYSQL_ROOT_PASSWORD="$(docker exec "${DB_CONTAINER}" printenv MYSQL_ROOT_PASSWORD || true)"
fi

if [ -z "${MYSQL_ROOT_PASSWORD}" ]; then
    echo "ERROR: MYSQL_ROOT_PASSWORD is not set or accessible in container." >&2
    exit 1
fi

# Execute transaction-consistent dump
echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Streaming compressed dump to ${BACKUP_FILE}..."
docker exec "${DB_CONTAINER}" mysqldump \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    -u root \
    -p"${MYSQL_ROOT_PASSWORD}" \
    "${DB_NAME}" | gzip -c > "${BACKUP_FILE}"

# Verify file existence and non-zero size
if [ ! -s "${BACKUP_FILE}" ]; then
    echo "ERROR: Backup file ${BACKUP_FILE} is empty or was not created!" >&2
    rm -f "${BACKUP_FILE}"
    exit 1
fi

# Generate SHA-256 checksum
(cd "${BACKUP_DIR}" && sha256sum "$(basename "${BACKUP_FILE}")" > "$(basename "${CHECKSUM_FILE}")")
chmod 600 "${BACKUP_FILE}" "${CHECKSUM_FILE}" 2>/dev/null || true

FILE_SIZE="$(du -h "${BACKUP_FILE}" | cut -f1)"
echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Backup completed successfully: ${BACKUP_FILE} (${FILE_SIZE})"
echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] SHA-256 Checksum: $(cat "${CHECKSUM_FILE}")"

# Prune old backups past retention threshold
echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Pruning backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -type f -name "hris_db_*.sql.gz*" -mtime +"${RETENTION_DAYS}" -delete 2>/dev/null || true

echo "[$(date -u +"%Y-%m-%d %H:%M:%SZ")] Backup routine finished successfully."
exit 0
