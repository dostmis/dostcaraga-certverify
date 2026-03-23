#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 /path/to/app-storage.tar.gz"
    exit 1
fi

ARCHIVE_FILE="$1"

if [[ ! -f "$ARCHIVE_FILE" ]]; then
    echo "Storage archive not found: $ARCHIVE_FILE" >&2
    exit 1
fi

if [[ "${CONFIRM_RESTORE_STORAGE:-}" != "YES" ]]; then
    echo "Set CONFIRM_RESTORE_STORAGE=YES to continue."
    echo "This operation overwrites files in the Docker app storage volume."
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

"${COMPOSE_CMD[@]}" up -d app >/dev/null

APP_CONTAINER_ID="$("${COMPOSE_CMD[@]}" ps -q app)"
if [[ -z "$APP_CONTAINER_ID" ]]; then
    echo "Unable to resolve the app container ID." >&2
    exit 1
fi

CONTAINER_ARCHIVE="/tmp/$(basename "$ARCHIVE_FILE")"

docker cp "$ARCHIVE_FILE" "${APP_CONTAINER_ID}:${CONTAINER_ARCHIVE}"

"${COMPOSE_CMD[@]}" exec -T app sh -lc '
set -eu
archive="$1"
first_entry="$(tar -tzf "$archive" | head -n 1 || true)"

if [ -z "$first_entry" ]; then
    echo "Storage archive is empty: $archive" >&2
    exit 1
fi

mkdir -p /var/www/html/storage
find /var/www/html/storage -mindepth 1 -maxdepth 1 -exec rm -rf {} +

case "$first_entry" in
    storage|storage/*)
        tar -xzf "$archive" -C /var/www/html
        ;;
    *)
        tar -xzf "$archive" -C /var/www/html/storage
        ;;
esac

chown -R www-data:www-data /var/www/html/storage
rm -f "$archive"
' restore-app-storage "$CONTAINER_ARCHIVE"

echo "Restored storage archive from $ARCHIVE_FILE"
echo "If the old server stored certificate PDFs on the public disk, run:"
echo "  ${COMPOSE_CMD[*]} exec app php artisan certificates:migrate-private-storage"
