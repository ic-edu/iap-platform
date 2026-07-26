#!/usr/bin/env bash
set -e

TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_DIR="./storage/backups"
BACKUP_FILE="${BACKUP_DIR}/iap_backup_${TIMESTAMP}.sql.gz"

mkdir -p ${BACKUP_DIR}

echo "📦 Starting Database Backup..."
mysqldump -h ${DB_HOST:-127.0.0.1} -u ${DB_USERNAME:-root} -p${DB_PASSWORD:-} ${DB_DATABASE:-iap_db} | gzip > ${BACKUP_FILE}

echo "✅ Backup generated at ${BACKUP_FILE}"
