#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_TIMEZONE="${BACKUP_TIMEZONE:-Asia/Manila}"
BACKUP_TZ_LABEL="${BACKUP_TZ_LABEL:-PHT}"
OUTPUT_FILE="${1:-./cert-verify_full-clone_$(TZ="$BACKUP_TIMEZONE" date +%Y%m%dT%H%M%S)${BACKUP_TZ_LABEL}.tar.gz}"
OUTPUT_DIR="$(dirname "$OUTPUT_FILE")"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

mkdir -p "$OUTPUT_DIR"

if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD=(docker-compose)
else
    echo "Docker Compose command not found. Install the Compose plugin or docker-compose." >&2
    exit 1
fi

"${COMPOSE_CMD[@]}" up -d app db >/dev/null

"${COMPOSE_CMD[@]}" exec -T db sh -lc '
set -eu
export PGPASSWORD="${POSTGRES_PASSWORD:-}"
pg_dump \
    --host=127.0.0.1 \
    --port=5432 \
    --username="${POSTGRES_USER:-postgres}" \
    --dbname="${POSTGRES_DB:-postgres}" \
    --format=plain \
    --no-owner \
    --no-privileges
' | gzip -c > "${TMP_DIR}/db.sql.gz"

"${SCRIPT_DIR}/backup-app-storage.sh" "${TMP_DIR}/app-storage.tar.gz"
"${SCRIPT_DIR}/backup-public-storage.sh" "${TMP_DIR}/public-storage.tar.gz"

cat > "${TMP_DIR}/manifest.txt" <<EOF
created_at=$(TZ="$BACKUP_TIMEZONE" date +%Y-%m-%dT%H:%M:%S)
created_at_timezone_label=${BACKUP_TZ_LABEL}
created_at_timezone_name=${BACKUP_TIMEZONE}
bundle_type=cert-verify-full-clone
bundle_version=2
db_backup=db.sql.gz
storage_backup=app-storage.tar.gz
public_storage_backup=public-storage.tar.gz
include_env=${INCLUDE_ENV:-false}
EOF

if [[ "${INCLUDE_ENV:-false}" == "true" ]] && [[ -f .env ]]; then
    cp .env "${TMP_DIR}/.env.snapshot"
fi

tar -czf "$OUTPUT_FILE" -C "$TMP_DIR" .

echo "Created full system clone at $OUTPUT_FILE"
echo "Bundle contents:"
echo "  - db.sql.gz"
echo "  - app-storage.tar.gz"
echo "  - public-storage.tar.gz"
if [[ -f "${TMP_DIR}/.env.snapshot" ]]; then
    echo "  - .env.snapshot"
fi
