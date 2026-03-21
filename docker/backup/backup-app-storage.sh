#!/bin/sh
set -eu

BACKUP_DIR="${BACKUP_DIR:-/backups}"
APP_STORAGE_SOURCE="${APP_STORAGE_SOURCE:-/app-storage}"
KEEP_MIN_FILES="${BACKUP_KEEP_MIN_FILES:-10}"
MAX_FILES="${BACKUP_MAX_FILES:-$KEEP_MIN_FILES}"
BACKUP_TIMEZONE="${BACKUP_TIMEZONE:-Asia/Manila}"
BACKUP_TZ_LABEL="${BACKUP_TZ_LABEL:-PHT}"

timestamp="$(TZ="$BACKUP_TIMEZONE" date +%Y%m%dT%H%M%S)${BACKUP_TZ_LABEL}"
archive_file="${BACKUP_DIR}/app-storage_${timestamp}.tar.gz"

mkdir -p "$BACKUP_DIR"

log_timestamp() {
    printf '%s %s' "$(TZ="$BACKUP_TIMEZONE" date +%Y-%m-%dT%H:%M:%S)" "$BACKUP_TZ_LABEL"
}

echo "[$(log_timestamp)] Starting app storage backup from ${APP_STORAGE_SOURCE}"

cleanup_by_pattern() {
    pattern="$1"

    total_files="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name "$pattern" | wc -l | tr -d ' ')"
    removable="$((total_files - MAX_FILES))"

    if [ "$removable" -le 0 ]; then
        return 0
    fi

    find "$BACKUP_DIR" -maxdepth 1 -type f -name "$pattern" \
        | sort \
        | head -n "$removable" \
        | while IFS= read -r old_file; do
            [ -n "$old_file" ] || continue
            rm -f "$old_file"
            echo "[$(log_timestamp)] Deleted old storage backup to enforce max files (${MAX_FILES}): $(basename "$old_file")"
        done
}

dirs=""
for dir in app/private app/public; do
    if [ -d "${APP_STORAGE_SOURCE}/${dir}" ]; then
        dirs="${dirs} ${dir}"
    fi
done

if [ -z "$dirs" ]; then
    echo "[$(log_timestamp)] App storage source does not contain app/private or app/public: ${APP_STORAGE_SOURCE}" >&2
    exit 1
fi

# shellcheck disable=SC2086
tar -czf "$archive_file" -C "$APP_STORAGE_SOURCE" $dirs

if [ ! -f "$archive_file" ]; then
    echo "[$(log_timestamp)] App storage backup failed: output file was not created" >&2
    exit 1
fi

size_bytes="$(wc -c < "$archive_file" | tr -d ' ')"
echo "[$(log_timestamp)] App storage backup completed: $(basename "$archive_file") (${size_bytes} bytes)"

cleanup_by_pattern "app-storage_*.tar.gz"

echo "[$(log_timestamp)] App storage backup run finished"
