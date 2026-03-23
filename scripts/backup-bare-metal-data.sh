#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${ENV_FILE:-${PROJECT_DIR}/.env}"
BACKUP_TIMEZONE="${BACKUP_TIMEZONE:-Asia/Manila}"
BACKUP_TZ_LABEL="${BACKUP_TZ_LABEL:-PHT}"
BACKUP_BASE_DIR="${BACKUP_TARGET_DIR:-${PROJECT_DIR}/backups}"
STAMP="$(TZ="${BACKUP_TIMEZONE}" date +%Y%m%dT%H%M%S)${BACKUP_TZ_LABEL}"
OUTPUT_FILE="${1:-${BACKUP_BASE_DIR}/data-only/cert-verify_data-only_${STAMP}.tar.gz}"
OUTPUT_DIR="$(dirname "${OUTPUT_FILE}")"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "${TMP_DIR}"' EXIT

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Required command not found: $1" >&2
        exit 1
    fi
}

read_env_value() {
    local key="$1"
    local raw

    if [[ ! -f "${ENV_FILE}" ]]; then
        return 1
    fi

    raw="$(awk -F= -v target="${key}" '$1 == target {sub(/^[^=]*=/, ""); print; exit}' "${ENV_FILE}")"
    raw="${raw%$'\r'}"

    if [[ "${raw}" =~ ^\"(.*)\"$ ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi

    if [[ "${raw}" =~ ^\'(.*)\'$ ]]; then
        printf '%s' "${BASH_REMATCH[1]}"
        return 0
    fi

    printf '%s' "${raw}"
}

require_command pg_dump
require_command tar

mkdir -p "${OUTPUT_DIR}"
cd "${PROJECT_DIR}"

DB_HOST="${DB_HOST:-$(read_env_value DB_HOST)}"
DB_PORT="${DB_PORT:-$(read_env_value DB_PORT)}"
DB_DATABASE="${DB_DATABASE:-$(read_env_value DB_DATABASE)}"
DB_USERNAME="${DB_USERNAME:-$(read_env_value DB_USERNAME)}"
DB_PASSWORD="${DB_PASSWORD:-$(read_env_value DB_PASSWORD)}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"

if [[ -z "${DB_DATABASE}" || -z "${DB_USERNAME}" ]]; then
    echo "DB_DATABASE and DB_USERNAME must be set in ${ENV_FILE} or the current environment." >&2
    exit 1
fi

if [[ ! -d storage ]]; then
    echo "storage directory not found in ${PROJECT_DIR}" >&2
    exit 1
fi

if [[ ! -e public/storage && ! -L public/storage ]]; then
    echo "public/storage does not exist in ${PROJECT_DIR}" >&2
    exit 1
fi

export PGPASSWORD="${DB_PASSWORD}"
pg_dump \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --username="${DB_USERNAME}" \
    --dbname="${DB_DATABASE}" \
    --format=plain \
    --no-owner \
    --no-privileges \
    | gzip -c > "${TMP_DIR}/db.sql.gz"

tar -czf "${TMP_DIR}/storage.tar.gz" storage

PUBLIC_STORAGE_MODE="directory"
PUBLIC_STORAGE_TARGET=""
if [[ -L public/storage ]]; then
    PUBLIC_STORAGE_MODE="symlink"
    PUBLIC_STORAGE_TARGET="$(readlink public/storage || true)"
    tar -czhf "${TMP_DIR}/public-storage.tar.gz" public/storage
else
    tar -czf "${TMP_DIR}/public-storage.tar.gz" public/storage
fi

cat > "${TMP_DIR}/manifest.txt" <<EOF
created_at=$(TZ="${BACKUP_TIMEZONE}" date +%Y-%m-%dT%H:%M:%S)
created_at_timezone_label=${BACKUP_TZ_LABEL}
created_at_timezone_name=${BACKUP_TIMEZONE}
bundle_type=cert-verify-data-only
db_backup=db.sql.gz
storage_backup=storage.tar.gz
public_storage_backup=public-storage.tar.gz
public_storage_mode=${PUBLIC_STORAGE_MODE}
public_storage_target=${PUBLIC_STORAGE_TARGET}
db_host=${DB_HOST}
db_port=${DB_PORT}
db_database=${DB_DATABASE}
db_username=${DB_USERNAME}
EOF

tar -czf "${OUTPUT_FILE}" -C "${TMP_DIR}" .

echo "Created bare-metal data backup at ${OUTPUT_FILE}"
echo "Bundle contents:"
echo "  - db.sql.gz"
echo "  - storage.tar.gz"
echo "  - public-storage.tar.gz"
echo "  - manifest.txt"
