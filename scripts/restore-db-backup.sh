#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 /path/to/backup.sql|/path/to/backup.sql.gz|/path/to/backup.sql.gz.gpg"
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

if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD=(docker-compose)
else
    echo "Docker Compose command not found. Install the Compose plugin or docker-compose." >&2
    exit 1
fi

PSQL_CMD=("${COMPOSE_CMD[@]}" exec -T db psql -v ON_ERROR_STOP=1 -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}")

if [[ "$BACKUP_FILE" == *.sql.gz.gpg ]]; then
    if [[ -n "${DB_BACKUP_GPG_PASSPHRASE:-}" ]]; then
        gpg --batch --yes --pinentry-mode loopback --passphrase "$DB_BACKUP_GPG_PASSPHRASE" --decrypt "$BACKUP_FILE" \
            | gzip -dc \
            | "${PSQL_CMD[@]}"
    else
        gpg --batch --yes --decrypt "$BACKUP_FILE" \
            | gzip -dc \
            | "${PSQL_CMD[@]}"
    fi
elif [[ "$BACKUP_FILE" == *.gz ]]; then
    gzip -dc "$BACKUP_FILE" | "${PSQL_CMD[@]}"
else
    cat "$BACKUP_FILE" | "${PSQL_CMD[@]}"
fi

echo "Restore completed from $BACKUP_FILE"
