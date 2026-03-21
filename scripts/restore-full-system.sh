#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 /path/to/cert-verify_full-clone.tar.gz"
    exit 1
fi

BUNDLE_FILE="$1"

if [[ ! -f "$BUNDLE_FILE" ]]; then
    echo "Full clone bundle not found: $BUNDLE_FILE" >&2
    exit 1
fi

if [[ "${CONFIRM_RESTORE_FULL:-}" != "YES" ]]; then
    echo "Set CONFIRM_RESTORE_FULL=YES to continue."
    echo "This operation restores both the database and the app storage volume."
    exit 1
fi

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

tar -xzf "$BUNDLE_FILE" -C "$TMP_DIR"

if [[ ! -f "${TMP_DIR}/db.sql.gz" ]]; then
    echo "Bundle is missing db.sql.gz" >&2
    exit 1
fi

if [[ ! -f "${TMP_DIR}/app-storage.tar.gz" ]]; then
    echo "Bundle is missing app-storage.tar.gz" >&2
    exit 1
fi

CONFIRM_RESTORE=YES "${SCRIPT_DIR}/restore-db-backup.sh" "${TMP_DIR}/db.sql.gz"
CONFIRM_RESTORE_STORAGE=YES "${SCRIPT_DIR}/restore-app-storage.sh" "${TMP_DIR}/app-storage.tar.gz"

if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD=(docker-compose)
else
    echo "Docker Compose command not found. Install the Compose plugin or docker-compose." >&2
    exit 1
fi

"${COMPOSE_CMD[@]}" exec app php artisan certificates:migrate-private-storage
"${COMPOSE_CMD[@]}" exec app php artisan optimize:clear

if [[ -f "${TMP_DIR}/.env.snapshot" ]]; then
    echo "Bundle also contains .env.snapshot"
    echo "Review it manually before replacing your current .env:"
    echo "  ${TMP_DIR}/.env.snapshot"
fi

echo "Full system restore completed from $BUNDLE_FILE"
