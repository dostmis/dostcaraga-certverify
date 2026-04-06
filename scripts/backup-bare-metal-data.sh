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
LOCAL_BUNDLE_TMP=""
trap 'rm -rf "${TMP_DIR}"; [[ -n "${LOCAL_BUNDLE_TMP}" ]] && rm -f "${LOCAL_BUNDLE_TMP}"' EXIT

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        echo "Required command not found: $1" >&2
        exit 1
    fi
}

join_path() {
    local base="$1"
    local leaf="$2"

    base="${base%/}"
    leaf="${leaf#/}"

    if [[ -z "${base}" ]]; then
        printf '%s' "${leaf}"
        return 0
    fi

    printf '%s/%s' "${base}" "${leaf}"
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

OFFSITE_BACKUP_ENABLED="${OFFSITE_BACKUP_ENABLED:-$(read_env_value OFFSITE_BACKUP_ENABLED || true)}"
OFFSITE_BACKUP_METHOD="${OFFSITE_BACKUP_METHOD:-$(read_env_value OFFSITE_BACKUP_METHOD || true)}"
OFFSITE_BACKUP_HOST="${OFFSITE_BACKUP_HOST:-$(read_env_value OFFSITE_BACKUP_HOST || true)}"
OFFSITE_BACKUP_PORT="${OFFSITE_BACKUP_PORT:-$(read_env_value OFFSITE_BACKUP_PORT || true)}"
OFFSITE_BACKUP_USER="${OFFSITE_BACKUP_USER:-$(read_env_value OFFSITE_BACKUP_USER || true)}"
OFFSITE_BACKUP_PATH="${OFFSITE_BACKUP_PATH:-$(read_env_value OFFSITE_BACKUP_PATH || true)}"
OFFSITE_BACKUP_SSH_KEY="${OFFSITE_BACKUP_SSH_KEY:-$(read_env_value OFFSITE_BACKUP_SSH_KEY || true)}"
OFFSITE_BACKUP_KEEP_LOCAL="${OFFSITE_BACKUP_KEEP_LOCAL:-$(read_env_value OFFSITE_BACKUP_KEEP_LOCAL || true)}"

OFFSITE_BACKUP_ENABLED="${OFFSITE_BACKUP_ENABLED:-false}"
OFFSITE_BACKUP_METHOD="${OFFSITE_BACKUP_METHOD:-rsync}"
OFFSITE_BACKUP_PORT="${OFFSITE_BACKUP_PORT:-22}"
OFFSITE_BACKUP_KEEP_LOCAL="${OFFSITE_BACKUP_KEEP_LOCAL:-false}"

offsite_enabled="false"
case "${OFFSITE_BACKUP_ENABLED,,}" in
    1|true|yes|on)
        offsite_enabled="true"
        ;;
esac

if [[ "${offsite_enabled}" == "true" ]]; then
    if [[ -z "${OFFSITE_BACKUP_HOST}" || -z "${OFFSITE_BACKUP_USER}" || -z "${OFFSITE_BACKUP_PATH}" ]]; then
        echo "OFFSITE_BACKUP_HOST, OFFSITE_BACKUP_USER, and OFFSITE_BACKUP_PATH are required when OFFSITE_BACKUP_ENABLED=true." >&2
        exit 1
    fi

    case "${OFFSITE_BACKUP_METHOD}" in
        rsync)
            require_command rsync
            require_command ssh
            ;;
        scp)
            require_command scp
            require_command ssh
            ;;
        *)
            echo "Unsupported OFFSITE_BACKUP_METHOD: ${OFFSITE_BACKUP_METHOD}. Use rsync or scp." >&2
            exit 1
            ;;
    esac
fi

if [[ "${offsite_enabled}" == "true" ]]; then
    LOCAL_BUNDLE_TMP="$(mktemp "${TMPDIR:-/tmp}/cert-verify_data-only_${STAMP}_XXXXXX.tar.gz")"
    LOCAL_BUNDLE_FILE="${LOCAL_BUNDLE_TMP}"
else
    LOCAL_BUNDLE_FILE="${OUTPUT_FILE}"
    mkdir -p "${OUTPUT_DIR}"
fi

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

PUBLIC_STORAGE_MODE="missing"
PUBLIC_STORAGE_TARGET=""
if [[ -L public/storage ]]; then
    PUBLIC_STORAGE_MODE="symlink"
    PUBLIC_STORAGE_TARGET="$(readlink public/storage || true)"
    tar -czhf "${TMP_DIR}/public-storage.tar.gz" public/storage
elif [[ -d public/storage ]]; then
    PUBLIC_STORAGE_MODE="directory"
    tar -czf "${TMP_DIR}/public-storage.tar.gz" public/storage
else
    tar -czf "${TMP_DIR}/public-storage.tar.gz" --files-from /dev/null
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

tar -czf "${LOCAL_BUNDLE_FILE}" -C "${TMP_DIR}" .

if [[ "${offsite_enabled}" == "true" ]]; then
    ssh_opts=(-p "${OFFSITE_BACKUP_PORT}" -o BatchMode=yes)
    if [[ -n "${OFFSITE_BACKUP_SSH_KEY}" ]]; then
        ssh_opts+=(-i "${OFFSITE_BACKUP_SSH_KEY}")
    fi

    remote_dir="${OFFSITE_BACKUP_PATH%/}"
    remote_file="$(join_path "${remote_dir}" "$(basename "${LOCAL_BUNDLE_FILE}")")"
    remote_host="${OFFSITE_BACKUP_USER}@${OFFSITE_BACKUP_HOST}"

    ssh "${ssh_opts[@]}" "${remote_host}" "mkdir -p \"${remote_dir}\""

    case "${OFFSITE_BACKUP_METHOD}" in
        rsync)
            rsync -az --progress -e "ssh -p ${OFFSITE_BACKUP_PORT}${OFFSITE_BACKUP_SSH_KEY:+ -i ${OFFSITE_BACKUP_SSH_KEY}}" \
                "${LOCAL_BUNDLE_FILE}" "${remote_host}:${remote_file}"
            ;;
        scp)
            scp "${ssh_opts[@]}" "${LOCAL_BUNDLE_FILE}" "${remote_host}:${remote_file}"
            ;;
    esac

    echo "Created bare-metal data backup and uploaded it to ${remote_host}:${remote_file}"

    case "${OFFSITE_BACKUP_KEEP_LOCAL,,}" in
        1|true|yes|on)
            mkdir -p "${OUTPUT_DIR}"
            cp "${LOCAL_BUNDLE_FILE}" "${OUTPUT_FILE}"
            echo "Kept a local copy at ${OUTPUT_FILE}"
            ;;
    esac
else
    echo "Created bare-metal data backup at ${OUTPUT_FILE}"
fi

echo "Bundle contents:"
echo "  - db.sql.gz"
echo "  - storage.tar.gz"
echo "  - public-storage.tar.gz"
echo "  - manifest.txt"
