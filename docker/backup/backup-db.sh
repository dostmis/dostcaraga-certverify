#!/bin/sh
set -eu

BACKUP_DIR="${BACKUP_DIR:-/backups}"
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-postgres}"
DB_USERNAME="${DB_USERNAME:-postgres}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"
KEEP_MIN_FILES="${BACKUP_KEEP_MIN_FILES:-10}"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
raw_file="${BACKUP_DIR}/${DB_DATABASE}_${timestamp}.sql"
gzip_file="${raw_file}.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Starting backup for ${DB_DATABASE} (${DB_HOST}:${DB_PORT})"

cleanup_tmp() {
    rm -f "$raw_file"
}

trap cleanup_tmp EXIT

export PGPASSWORD="${DB_PASSWORD:-}"

pg_dump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --username="$DB_USERNAME" \
    --dbname="$DB_DATABASE" \
    --format=plain \
    --no-owner \
    --no-privileges \
    > "$raw_file"

gzip -f "$raw_file"
trap - EXIT

if [ ! -f "$gzip_file" ]; then
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Backup failed: output file was not created" >&2
    exit 1
fi

size_bytes="$(wc -c < "$gzip_file" | tr -d ' ')"
echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Backup completed: $(basename "$gzip_file") (${size_bytes} bytes)"

total_files="$(find "$BACKUP_DIR" -maxdepth 1 -type f -name "${DB_DATABASE}_*.sql.gz" | wc -l | tr -d ' ')"
removable="$((total_files - KEEP_MIN_FILES))"

if [ "$removable" -gt 0 ]; then
    find "$BACKUP_DIR" -maxdepth 1 -type f -name "${DB_DATABASE}_*.sql.gz" -mtime "+${RETENTION_DAYS}" \
        | sort \
        | head -n "$removable" \
        | while IFS= read -r old_file; do
            [ -n "$old_file" ] || continue
            rm -f "$old_file"
            echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Deleted expired backup: $(basename "$old_file")"
        done
fi
