#!/bin/sh
set -eu

BACKUP_CRON_SCHEDULE="${BACKUP_CRON_SCHEDULE:-0 */6 * * *}"
BACKUP_RUN_ON_START="${BACKUP_RUN_ON_START:-true}"
BACKUP_DIR="${BACKUP_DIR:-/backups}"
CRON_FILE="/etc/crontabs/root"

mkdir -p "$BACKUP_DIR"

if [ "$BACKUP_RUN_ON_START" = "true" ] || [ "$BACKUP_RUN_ON_START" = "1" ]; then
    /bin/sh /usr/local/bin/backup-db.sh
fi

printf '%s /bin/sh /usr/local/bin/backup-db.sh >> /proc/1/fd/1 2>> /proc/1/fd/2\n' "$BACKUP_CRON_SCHEDULE" > "$CRON_FILE"

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Backup schedule set to: ${BACKUP_CRON_SCHEDULE}"

exec crond -f -l 8
