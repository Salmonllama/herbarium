#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEPLOY_DIR="${ROOT_DIR}/deploy"
ENV_FILE="${DEPLOY_DIR}/.env"

if [[ ! -f "$ENV_FILE" ]]; then
    echo "Error: ${ENV_FILE} not found. Run ./install.sh first." >&2
    exit 1
fi

echo "[upgrade] Pulling latest Herbarium code…"
git -C "$ROOT_DIR" pull --ff-only

echo "[upgrade] Rebuilding application containers…"
docker compose -f "${DEPLOY_DIR}/docker-compose.yml" --env-file "${ENV_FILE}" build api web

echo "[upgrade] Applying database migrations…"
docker compose -f "${DEPLOY_DIR}/docker-compose.yml" --env-file "${ENV_FILE}" run --rm api php artisan migrate --force
docker compose -f "${DEPLOY_DIR}/docker-compose.yml" --env-file "${ENV_FILE}" run --rm api php artisan optimize

echo "[upgrade] Restarting services…"
docker compose -f "${DEPLOY_DIR}/docker-compose.yml" --env-file "${ENV_FILE}" up -d

echo "[upgrade] Herbarium upgraded cleanly."
