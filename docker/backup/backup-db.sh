#!/bin/sh
set -eu

BACKUP_DIR="${BACKUP_DIR:-/backups}"
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-5432}"
DB_DATABASE="${DB_DATABASE:-postgres}"
DB_USERNAME="${DB_USERNAME:-postgres}"
KEEP_MIN_FILES="${BACKUP_KEEP_MIN_FILES:-10}"
MAX_FILES="${BACKUP_MAX_FILES:-$KEEP_MIN_FILES}"
GPG_ENABLED="${BACKUP_GPG_ENABLED:-false}"
GPG_PASSPHRASE="${BACKUP_GPG_PASSPHRASE:-}"
GPG_RECIPIENT="${BACKUP_GPG_RECIPIENT:-}"
KEEP_PLAIN="${BACKUP_KEEP_PLAIN:-false}"

timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
raw_file="${BACKUP_DIR}/${DB_DATABASE}_${timestamp}.sql"
gzip_file="${raw_file}.gz"
gpg_file="${gzip_file}.gpg"

mkdir -p "$BACKUP_DIR"

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Starting backup for ${DB_DATABASE} (${DB_HOST}:${DB_PORT})"

is_true() {
    case "$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')" in
        1|true|yes|on) return 0 ;;
        *) return 1 ;;
    esac
}

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
            echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Deleted old backup to enforce max files (${MAX_FILES}): $(basename "$old_file")"
        done
}

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

if is_true "$GPG_ENABLED"; then
    if [ -n "$GPG_RECIPIENT" ]; then
        gpg --batch --yes --trust-model always \
            --encrypt --recipient "$GPG_RECIPIENT" \
            --output "$gpg_file" "$gzip_file"
    elif [ -n "$GPG_PASSPHRASE" ]; then
        gpg --batch --yes --pinentry-mode loopback \
            --symmetric --cipher-algo AES256 \
            --passphrase "$GPG_PASSPHRASE" \
            --output "$gpg_file" "$gzip_file"
    else
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] BACKUP_GPG_ENABLED=true but no BACKUP_GPG_PASSPHRASE or BACKUP_GPG_RECIPIENT was provided" >&2
        exit 1
    fi

    gpg_size_bytes="$(wc -c < "$gpg_file" | tr -d ' ')"
    echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Encrypted backup completed: $(basename "$gpg_file") (${gpg_size_bytes} bytes)"

    if ! is_true "$KEEP_PLAIN"; then
        rm -f "$gzip_file"
        echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Removed unencrypted dump: $(basename "$gzip_file")"
    fi
fi

if ! is_true "$GPG_ENABLED" || is_true "$KEEP_PLAIN"; then
    cleanup_by_pattern "${DB_DATABASE}_*.sql.gz"
fi

if is_true "$GPG_ENABLED"; then
    cleanup_by_pattern "${DB_DATABASE}_*.sql.gz.gpg"
fi

echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] Backup run finished"
