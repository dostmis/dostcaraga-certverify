#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${ENV_FILE:-${PROJECT_DIR}/.env}"

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 /path/to/cert-verify_data-only.tar.gz"
    exit 1
fi

BUNDLE_FILE="$1"

if [[ ! -f "${BUNDLE_FILE}" ]]; then
    echo "Backup bundle not found: ${BUNDLE_FILE}" >&2
    exit 1
fi

if [[ "${CONFIRM_RESTORE_BARE_METAL:-}" != "YES" ]]; then
    echo "Set CONFIRM_RESTORE_BARE_METAL=YES to continue."
    echo "This operation overwrites the target database, storage/, and public/storage."
    exit 1
fi

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

read_manifest_value() {
    local key="$1"
    local manifest_file="${TMP_DIR}/manifest.txt"

    if [[ ! -f "${manifest_file}" ]]; then
        return 1
    fi

    awk -F= -v target="${key}" '$1 == target {sub(/^[^=]*=/, ""); print; exit}' "${manifest_file}"
}

require_command tar
require_command gzip
require_command psql
require_command php

cd "${PROJECT_DIR}"
tar -xzf "${BUNDLE_FILE}" -C "${TMP_DIR}"

if [[ ! -f "${TMP_DIR}/db.sql.gz" ]]; then
    echo "Bundle is missing db.sql.gz" >&2
    exit 1
fi

if [[ ! -f "${TMP_DIR}/storage.tar.gz" ]]; then
    echo "Bundle is missing storage.tar.gz" >&2
    exit 1
fi

DB_HOST="${DB_HOST:-$(read_env_value DB_HOST)}"
DB_PORT="${DB_PORT:-$(read_env_value DB_PORT)}"
DB_DATABASE="${DB_DATABASE:-$(read_env_value DB_DATABASE)}"
DB_USERNAME="${DB_USERNAME:-$(read_env_value DB_USERNAME)}"
DB_PASSWORD="${DB_PASSWORD:-$(read_env_value DB_PASSWORD)}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"

if [[ -z "${DB_DATABASE}" || -z "${DB_USERNAME}" ]]; then
    echo "DB_DATABASE and DB_USERNAME must be set in ${ENV_FILE} or the current environment." >&2
    echo "Clone the repo and configure the new server .env first." >&2
    exit 1
fi

PUBLIC_STORAGE_MODE="$(read_manifest_value public_storage_mode || true)"
PUBLIC_STORAGE_MODE="${PUBLIC_STORAGE_MODE:-symlink}"

export PGPASSWORD="${DB_PASSWORD}"

psql \
    -v ON_ERROR_STOP=1 \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --username="${DB_USERNAME}" \
    --dbname="${DB_DATABASE}" \
    -c 'DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;'

gzip -dc "${TMP_DIR}/db.sql.gz" | psql \
    -v ON_ERROR_STOP=1 \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --username="${DB_USERNAME}" \
    --dbname="${DB_DATABASE}"

rm -rf "${PROJECT_DIR}/storage"
mkdir -p "${PROJECT_DIR}"
tar -xzf "${TMP_DIR}/storage.tar.gz" -C "${PROJECT_DIR}"

rm -rf "${PROJECT_DIR}/public/storage"

if [[ "${PUBLIC_STORAGE_MODE}" == "directory" ]]; then
    if [[ ! -f "${TMP_DIR}/public-storage.tar.gz" ]]; then
        echo "Bundle is missing public-storage.tar.gz" >&2
        exit 1
    fi

    tar -xzf "${TMP_DIR}/public-storage.tar.gz" -C "${PROJECT_DIR}"
else
    php artisan storage:link --force >/dev/null 2>&1
fi

php artisan migrate --force
php artisan optimize:clear

if id www-data >/dev/null 2>&1; then
    echo "If your web server runs as www-data, apply permissions with:"
    echo "  sudo chown -R www-data:www-data ${PROJECT_DIR}/storage ${PROJECT_DIR}/bootstrap/cache"
fi

echo "Bare-metal restore completed from ${BUNDLE_FILE}"
echo "Database restored to ${DB_DATABASE} on ${DB_HOST}:${DB_PORT}"
echo "storage/ restored under ${PROJECT_DIR}/storage"
if [[ "${PUBLIC_STORAGE_MODE}" == "directory" ]]; then
    echo "public/storage restored as a real directory"
else
    echo "public/storage recreated as a symlink"
fi
