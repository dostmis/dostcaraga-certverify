#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 ]]; then
    echo "Usage: $0 /path/to/public-storage.tar.gz"
    exit 1
fi

ARCHIVE_FILE="$1"

if [[ ! -f "$ARCHIVE_FILE" ]]; then
    echo "Public storage archive not found: $ARCHIVE_FILE" >&2
    exit 1
fi

if [[ "${CONFIRM_RESTORE_PUBLIC_STORAGE:-}" != "YES" ]]; then
    echo "Set CONFIRM_RESTORE_PUBLIC_STORAGE=YES to continue."
    echo "This operation restores the public/storage link state for the Docker app container."
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
tmp_dir="$(mktemp -d)"
trap '"'"'rm -rf "$tmp_dir" "$archive"'"'"' EXIT

tar -xzf "$archive" -C "$tmp_dir"

if [ -L "$tmp_dir/storage" ]; then
    :
elif [ -d "$tmp_dir/storage" ]; then
    mkdir -p /var/www/html/storage/app/public
    cp -a "$tmp_dir/storage/." /var/www/html/storage/app/public/
else
    echo "Archive does not contain a public/storage entry." >&2
    exit 1
fi

php /var/www/html/artisan storage:link --force >/dev/null 2>&1 || true
chown -R www-data:www-data /var/www/html/storage
' restore-public-storage "$CONTAINER_ARCHIVE"

echo "Restored public/storage state from $ARCHIVE_FILE"
