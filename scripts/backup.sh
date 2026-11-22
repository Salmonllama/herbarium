#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_DIR="${ROOT_DIR}/deploy"
ENV_FILE="${DEPLOY_DIR}/.env"
BACKUP_DIR="${ROOT_DIR}/backups"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Error: ${ENV_FILE} not found. Run ./install.sh first." >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

echo "[backup] Dumping PostgreSQL + PostGIS database…"
docker compose -f "${DEPLOY_DIR}/docker-compose.yml" --env-file "${ENV_FILE}" \
    exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -Fc "$POSTGRES_DB"' \
    > "${BACKUP_DIR}/herbarium-db-${STAMP}.dump"

echo "[backup] Archiving local media volume…"
docker compose -f "${DEPLOY_DIR}/docker-compose.yml" --env-file "${ENV_FILE}" \
    exec -T api tar -czf - -C /var/www/html/storage/app . \
    > "${BACKUP_DIR}/herbarium-media-${STAMP}.tar.gz"

echo "[backup] Complete:"
ls -lh "${BACKUP_DIR}/herbarium-db-${STAMP}.dump" "${BACKUP_DIR}/herbarium-media-${STAMP}.tar.gz"
