#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-$HOME/Documents/cert-verify}"
OUT_DIR="${OUT_DIR:-$PROJECT_DIR/db}"
MAX_FILES="${MAX_FILES:-10}"

mkdir -p "$OUT_DIR"
TS="$(date -u +%Y%m%dT%H%M%SZ)"

cd "$PROJECT_DIR"

if docker compose version >/dev/null 2>&1; then
  COMPOSE_CMD=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  COMPOSE_CMD=(docker-compose)
else
  echo "Docker Compose is not available (docker compose / docker-compose)." >&2
  exit 1
fi

"${COMPOSE_CMD[@]}" exec -T db sh -lc 'export PGPASSWORD="$POSTGRES_PASSWORD"; pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-privileges' \
  | gzip > "$OUT_DIR/certverify_${TS}.sql.gz"

# Keep only latest MAX_FILES backups
if [[ "$MAX_FILES" =~ ^[0-9]+$ ]] && [ "$MAX_FILES" -gt 0 ]; then
  find "$OUT_DIR" -maxdepth 1 -type f -name 'certverify_*.sql.gz' | sort | head -n -"$MAX_FILES" | xargs -r rm -f
fi
