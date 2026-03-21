#!/bin/sh
set -eu

BACKUP_CRON_SCHEDULE="${BACKUP_CRON_SCHEDULE:-0 */2 * * *}"
BACKUP_RUN_ON_START="${BACKUP_RUN_ON_START:-true}"
BACKUP_DIR="${BACKUP_DIR:-/backups}"
BACKUP_SCRIPT="${BACKUP_SCRIPT:-/usr/local/bin/backup-db.sh}"
BACKUP_TIMEZONE="${BACKUP_TIMEZONE:-Asia/Manila}"
BACKUP_TZ_LABEL="${BACKUP_TZ_LABEL:-PHT}"
CRON_FILE="/etc/crontabs/root"

mkdir -p "$BACKUP_DIR"

if [ ! -x "$BACKUP_SCRIPT" ]; then
    echo "[$(TZ="$BACKUP_TIMEZONE" date +%Y-%m-%dT%H:%M:%S) ${BACKUP_TZ_LABEL}] Backup script is not executable: ${BACKUP_SCRIPT}" >&2
    exit 1
fi

if [ "$BACKUP_RUN_ON_START" = "true" ] || [ "$BACKUP_RUN_ON_START" = "1" ]; then
    /bin/sh "$BACKUP_SCRIPT"
fi

printf '%s /bin/sh %s >> /proc/1/fd/1 2>> /proc/1/fd/2\n' "$BACKUP_CRON_SCHEDULE" "$BACKUP_SCRIPT" > "$CRON_FILE"

echo "[$(TZ="$BACKUP_TIMEZONE" date +%Y-%m-%dT%H:%M:%S) ${BACKUP_TZ_LABEL}] Backup script: ${BACKUP_SCRIPT}"
echo "[$(TZ="$BACKUP_TIMEZONE" date +%Y-%m-%dT%H:%M:%S) ${BACKUP_TZ_LABEL}] Backup schedule set to: ${BACKUP_CRON_SCHEDULE}"

exec crond -f -l 8
