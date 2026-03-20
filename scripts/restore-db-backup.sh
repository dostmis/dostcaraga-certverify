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

if [[ "$BACKUP_FILE" == *.sql.gz.gpg ]]; then
    if [[ -n "${DB_BACKUP_GPG_PASSPHRASE:-}" ]]; then
        gpg --batch --yes --pinentry-mode loopback --passphrase "$DB_BACKUP_GPG_PASSPHRASE" --decrypt "$BACKUP_FILE" \
            | gzip -dc \
            | docker compose exec -T db psql -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}"
    else
        gpg --batch --yes --decrypt "$BACKUP_FILE" \
            | gzip -dc \
            | docker compose exec -T db psql -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}"
    fi
elif [[ "$BACKUP_FILE" == *.gz ]]; then
    gzip -dc "$BACKUP_FILE" | docker compose exec -T db psql -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}"
else
    cat "$BACKUP_FILE" | docker compose exec -T db psql -U "${DB_USERNAME:-certverify_user}" -d "${DB_DATABASE:-certverify}"
fi

echo "Restore completed from $BACKUP_FILE"
