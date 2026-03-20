#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 /path/to/backup.sql.gz"
    exit 1
fi

BACKUP_FILE="$1"

if [[ ! -f "$BACKUP_FILE" ]]; then
    echo "Backup file not found: $BACKUP_FILE" >&2
    exit 1
fi

if [[ "${CONFIRM_RESTORE:-}" != "YES" ]]; then
    echo "Set CONFIRM_RESTORE=YES to continue."
    echo "This operation overwrites data in the target database."
    exit 1
fi

if [[ "$BACKUP_FILE" == *.gz ]]; then
    gzip -dc "$BACKUP_FILE" | docker compose exec -T db psql -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}"
else
    cat "$BACKUP_FILE" | docker compose exec -T db psql -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}"
fi

echo "Restore completed from $BACKUP_FILE"
