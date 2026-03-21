#!/usr/bin/env bash
set -euo pipefail

BACKUP_TIMEZONE="${BACKUP_TIMEZONE:-Asia/Manila}"
BACKUP_TZ_LABEL="${BACKUP_TZ_LABEL:-PHT}"
OUTPUT_FILE="${1:-./app-storage_$(TZ="$BACKUP_TIMEZONE" date +%Y%m%dT%H%M%S)${BACKUP_TZ_LABEL}.tar.gz}"
OUTPUT_DIR="$(dirname "$OUTPUT_FILE")"
OUTPUT_BASENAME="$(basename "$OUTPUT_FILE")"
CONTAINER_ARCHIVE="/tmp/${OUTPUT_BASENAME}"

mkdir -p "$OUTPUT_DIR"

if docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD=(docker-compose)
else
    echo "Docker Compose command not found. Install the Compose plugin or docker-compose." >&2
    exit 1
fi

"${COMPOSE_CMD[@]}" up -d app >/dev/null

APP_CONTAINER_ID="$("${COMPOSE_CMD[@]}" ps -q app)"
if [[ -z "$APP_CONTAINER_ID" ]]; then
    echo "Unable to resolve the app container ID." >&2
    exit 1
fi

"${COMPOSE_CMD[@]}" exec -T app sh -lc '
set -eu
archive="$1"
cd /var/www/html/storage

dirs=""
for dir in app/private app/public; do
    if [ -d "$dir" ]; then
        dirs="$dirs $dir"
    fi
done

if [ -z "$dirs" ]; then
    echo "No storage directories found to archive." >&2
    exit 1
fi

rm -f "$archive"
# shellcheck disable=SC2086
tar -czf "$archive" $dirs
' backup-app-storage "$CONTAINER_ARCHIVE"

docker cp "${APP_CONTAINER_ID}:${CONTAINER_ARCHIVE}" "$OUTPUT_FILE"
"${COMPOSE_CMD[@]}" exec -T app sh -lc 'rm -f "$1"' cleanup-app-storage "$CONTAINER_ARCHIVE"

echo "Created storage backup at $OUTPUT_FILE"
