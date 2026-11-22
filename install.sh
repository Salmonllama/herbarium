#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# Herbarium — Day-1 Self-Hosting Installer
# Sets up Caddy (Auto-TLS), Laravel API, Queue Worker, Scheduler, Next.js Web,
# PostgreSQL + PostGIS, Redis, and optional Meilisearch via Docker Compose.
# ==============================================================================

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_DIR="${ROOT_DIR}/deploy"
ENV_FILE="${DEPLOY_DIR}/.env"
CADDY_TEMPLATE="${DEPLOY_DIR}/caddy/Caddyfile.template"
CADDY_OUT="${DEPLOY_DIR}/caddy/Caddyfile"

NON_INTERACTIVE=false
DRY_RUN=false

usage() {
    cat <<EOF
Herbarium Day-1 Installer

Usage:
  ./install.sh [options]

Options:
  --non-interactive   Run without prompts using environment variables or defaults
  --dry-run           Generate deploy/.env and Caddyfile without starting containers
  -h, --help          Show this help message

Environment overrides for --non-interactive:
  HERBARIUM_DOMAIN            Domain or 'localhost' (default: localhost)
  HERBARIUM_HTTP_PORT         HTTP port (default: 80)
  HERBARIUM_HTTPS_PORT        HTTPS port (default: 443)
  HERBARIUM_APP_NAME          Instance display name (default: Herbarium)
  HERBARIUM_REGISTRATION_MODE invite_only | open (default: invite_only)
  HERBARIUM_PIN_DEFAULT       true | false (default: true)
  HERBARIUM_ADMIN_NAME        Initial admin name (default: Herbarium Curator)
  HERBARIUM_ADMIN_EMAIL       Initial admin email (default: admin@herbarium.local)
  HERBARIUM_ADMIN_PASSWORD    Initial admin password (auto-generated if empty)
  HERBARIUM_FIRST_NAME        First herbarium name (default: My Digital Herbarium)
  HERBARIUM_FIRST_PREFIX      Specimen ID prefix (default: HERB)
  HERBARIUM_SEED_DEMO         true | false (default: false)
  HERBARIUM_MEDIA_DISK        local | s3 (default: local)
  HERBARIUM_ENABLE_MEILI      true | false (default: false)
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --non-interactive) NON_INTERACTIVE=true; shift ;;
        --dry-run) DRY_RUN=true; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1"; usage; exit 1 ;;
    esac
done

log() { printf "\033[1;32m[herbarium]\033[0m %s\n" "$1"; }
warn() { printf "\033[1;33m[warning]\033[0m %s\n" "$1"; }
err() { printf "\033[1;31m[error]\033[0m %s\n" "$1" >&2; }

prompt_val() {
    local var_name="$1"
    local label="$2"
    local default_val="$3"
    local current_val="${!var_name:-}"

    if [[ -n "$current_val" ]]; then
        printf -v "$var_name" "%s" "$current_val"
        return
    fi

    if [[ "$NON_INTERACTIVE" == "true" ]]; then
        printf -v "$var_name" "%s" "$default_val"
        return
    fi

    read -r -p "${label} [${default_val}]: " input_val
    printf -v "$var_name" "%s" "${input_val:-$default_val}"
}

prompt_secret() {
    local var_name="$1"
    local label="$2"
    local default_val="$3"
    local current_val="${!var_name:-}"

    if [[ -n "$current_val" ]]; then
        printf -v "$var_name" "%s" "$current_val"
        return
    fi

    if [[ "$NON_INTERACTIVE" == "true" ]]; then
        printf -v "$var_name" "%s" "$default_val"
        return
    fi

    read -r -s -p "${label} [leave blank to auto-generate]: " input_val
    echo ""
    printf -v "$var_name" "%s" "${input_val:-$default_val}"
}

rand_hex() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -hex "$1"
    else
        head -c "$1" /dev/urandom | od -An -tx1 | tr -d ' \n'
    fi
}

rand_base64_32() {
    if command -v openssl >/dev/null 2>&1; then
        openssl rand -base64 32
    else
        head -c 32 /dev/urandom | base64 | tr -d '\n'
    fi
}

check_prereqs() {
    if ! command -v docker >/dev/null 2>&1; then
        err "Docker is required. Install Docker Engine first: https://docs.docker.com/engine/install/"
        exit 1
    fi

    if ! docker compose version >/dev/null 2>&1; then
        err "Docker Compose v2 plugin ('docker compose') is required."
        exit 1
    fi
}

slugify() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+|-+$//g'
}

echo "================================================================"
echo "  Herbarium — Open-Source Digital Herbarium Day-1 Installer"
echo "================================================================"
echo ""

if [[ "$DRY_RUN" != "true" ]]; then
    check_prereqs
fi

HERBARIUM_DOMAIN="${HERBARIUM_DOMAIN:-}"
HERBARIUM_HTTP_PORT="${HERBARIUM_HTTP_PORT:-}"
HERBARIUM_HTTPS_PORT="${HERBARIUM_HTTPS_PORT:-}"
HERBARIUM_APP_NAME="${HERBARIUM_APP_NAME:-}"
HERBARIUM_REGISTRATION_MODE="${HERBARIUM_REGISTRATION_MODE:-}"
HERBARIUM_PIN_DEFAULT="${HERBARIUM_PIN_DEFAULT:-}"
HERBARIUM_ADMIN_NAME="${HERBARIUM_ADMIN_NAME:-}"
HERBARIUM_ADMIN_EMAIL="${HERBARIUM_ADMIN_EMAIL:-}"
HERBARIUM_ADMIN_PASSWORD="${HERBARIUM_ADMIN_PASSWORD:-}"
HERBARIUM_FIRST_NAME="${HERBARIUM_FIRST_NAME:-}"
HERBARIUM_FIRST_PREFIX="${HERBARIUM_FIRST_PREFIX:-}"
HERBARIUM_SEED_DEMO="${HERBARIUM_SEED_DEMO:-}"
HERBARIUM_MEDIA_DISK="${HERBARIUM_MEDIA_DISK:-}"
HERBARIUM_ENABLE_MEILI="${HERBARIUM_ENABLE_MEILI:-}"

prompt_val HERBARIUM_DOMAIN "Public domain (e.g. herbarium.univ.edu) or 'localhost'" "localhost"
prompt_val HERBARIUM_HTTP_PORT "HTTP port" "80"
prompt_val HERBARIUM_HTTPS_PORT "HTTPS port" "443"
prompt_val HERBARIUM_APP_NAME "Instance name" "Herbarium"
prompt_val HERBARIUM_REGISTRATION_MODE "Registration mode (invite_only / open)" "invite_only"
prompt_val HERBARIUM_FIRST_NAME "Initial Herbarium collection name" "My Digital Herbarium"
prompt_val HERBARIUM_FIRST_PREFIX "Specimen Catalog ID prefix (e.g. UC, OSC, HERB)" "HERB"
prompt_val HERBARIUM_PIN_DEFAULT "Pin this herbarium directly to root URL '/'? (true / false)" "true"
prompt_val HERBARIUM_ADMIN_NAME "Initial Admin full name" "Herbarium Curator"
prompt_val HERBARIUM_ADMIN_EMAIL "Initial Admin email" "admin@herbarium.local"
GENERATED_ADMIN_PW="$(rand_hex 10)"
prompt_secret HERBARIUM_ADMIN_PASSWORD "Initial Admin password" "${GENERATED_ADMIN_PW}"
prompt_val HERBARIUM_SEED_DEMO "Seed Pacific Northwest demo flora specimens? (true / false)" "false"
prompt_val HERBARIUM_MEDIA_DISK "Media storage driver (local / s3)" "local"
prompt_val HERBARIUM_ENABLE_MEILI "Enable optional Meilisearch container for 500k+ collections? (true / false)" "false"

FIRST_SLUG="$(slugify "$HERBARIUM_FIRST_NAME")"
[[ -z "$FIRST_SLUG" ]] && FIRST_SLUG="herbarium"
FIRST_PREFIX="$(echo "$HERBARIUM_FIRST_PREFIX" | tr '[:lower:]' '[:upper:]' | sed -E 's/[^A-Z0-9_-]//g')"
[[ -z "$FIRST_PREFIX" ]] && FIRST_PREFIX="HERB"

if [[ "$HERBARIUM_DOMAIN" == "localhost" || "$HERBARIUM_DOMAIN" == "127.0.0.1" ]]; then
    SITE_ADDRESS=":${HERBARIUM_HTTP_PORT}"
    if [[ "$HERBARIUM_HTTP_PORT" == "80" ]]; then
        APP_URL="http://${HERBARIUM_DOMAIN}"
        STATEFUL_DOMAINS="localhost,127.0.0.1"
    else
        APP_URL="http://${HERBARIUM_DOMAIN}:${HERBARIUM_HTTP_PORT}"
        STATEFUL_DOMAINS="localhost,localhost:${HERBARIUM_HTTP_PORT},127.0.0.1,127.0.0.1:${HERBARIUM_HTTP_PORT}"
    fi
else
    SITE_ADDRESS="${HERBARIUM_DOMAIN}"
    APP_URL="https://${HERBARIUM_DOMAIN}"
    STATEFUL_DOMAINS="${HERBARIUM_DOMAIN},localhost"
fi

DEFAULT_HERBARIUM_ENV=""
if [[ "$HERBARIUM_PIN_DEFAULT" == "true" ]]; then
    DEFAULT_HERBARIUM_ENV="${FIRST_SLUG}"
fi

SCOUT_DRIVER="database"
MEILI_KEY=""
if [[ "$HERBARIUM_ENABLE_MEILI" == "true" ]]; then
    SCOUT_DRIVER="meilisearch"
    MEILI_KEY="$(rand_hex 24)"
fi

APP_KEY="base64:$(rand_base64_32)"
DB_PASSWORD="$(rand_hex 20)"

if [[ -f "$ENV_FILE" ]]; then
    warn "Existing ${ENV_FILE} detected — preserving existing file (delete it first to regenerate secrets)."
else
    log "Generating ${ENV_FILE} with fresh cryptographic secrets…"
    cat > "$ENV_FILE" <<EOF
APP_NAME="${HERBARIUM_APP_NAME}"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=${APP_URL}

APP_REGISTRATION_MODE=${HERBARIUM_REGISTRATION_MODE}
APP_DEFAULT_HERBARIUM=${DEFAULT_HERBARIUM_ENV}

HTTP_PORT=${HERBARIUM_HTTP_PORT}
HTTPS_PORT=${HERBARIUM_HTTPS_PORT}

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=herbarium
DB_USERNAME=herbarium
DB_PASSWORD=${DB_PASSWORD}

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379

SANCTUM_STATEFUL_DOMAINS=${STATEFUL_DOMAINS}

SCOUT_DRIVER=${SCOUT_DRIVER}
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=${MEILI_KEY}

HERBARIUM_MEDIA_DISK=${HERBARIUM_MEDIA_DISK}
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false

MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@${HERBARIUM_DOMAIN}
MAIL_FROM_NAME="${HERBARIUM_APP_NAME}"
EOF
    chmod 600 "$ENV_FILE"
fi

log "Rendering Caddy reverse proxy configuration (${CADDY_OUT})…"
sed "s|__SITE_ADDRESS__|${SITE_ADDRESS}|g" "$CADDY_TEMPLATE" > "$CADDY_OUT"

if [[ "$DRY_RUN" == "true" ]]; then
    log "Dry run complete. Review ${ENV_FILE} and ${CADDY_OUT}."
    exit 0
fi

COMPOSE_ARGS=("-f" "${DEPLOY_DIR}/docker-compose.yml" "--env-file" "${ENV_FILE}")
if [[ "$HERBARIUM_ENABLE_MEILI" == "true" ]]; then
    COMPOSE_ARGS+=("--profile" "search")
fi

log "Starting PostgreSQL + PostGIS and Redis…"
docker compose "${COMPOSE_ARGS[@]}" up -d postgres redis

log "Building Herbarium API and Next.js Web containers…"
docker compose "${COMPOSE_ARGS[@]}" build api web

log "Running database migrations (PostGIS + pg_trgm + Herbarium schema)…"
docker compose "${COMPOSE_ARGS[@]}" run --rm api php artisan migrate --force

BOOTSTRAP_ARGS=(
    "--email=${HERBARIUM_ADMIN_EMAIL}"
    "--name=${HERBARIUM_ADMIN_NAME}"
    "--password=${HERBARIUM_ADMIN_PASSWORD}"
    "--herbarium=${HERBARIUM_FIRST_NAME}"
    "--slug=${FIRST_SLUG}"
    "--prefix=${FIRST_PREFIX}"
)
if [[ "$HERBARIUM_SEED_DEMO" == "true" ]]; then
    BOOTSTRAP_ARGS+=("--seed-demo")
fi

log "Bootstrapping initial administrator & herbarium workspace…"
docker compose "${COMPOSE_ARGS[@]}" run --rm api php artisan herbarium:bootstrap "${BOOTSTRAP_ARGS[@]}"

log "Starting all Herbarium services (caddy, api, worker, scheduler, web)…"
docker compose "${COMPOSE_ARGS[@]}" up -d

log "Waiting for Herbarium API health check…"
for i in $(seq 1 30); do
    if docker compose "${COMPOSE_ARGS[@]}" exec -T api wget -qO- http://127.0.0.1:8000/api/v1/health >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

echo ""
echo "================================================================"
echo "  Herbarium is live!"
echo "================================================================"
echo "  Public Portal:   ${APP_URL}"
echo "  Herbarium URL:   ${APP_URL}/h/${FIRST_SLUG}"
echo "  Workspace:       ${APP_URL}/app/${FIRST_SLUG}"
echo "  OpenAPI Docs:    ${APP_URL}/docs/api"
echo "  Admin Email:     ${HERBARIUM_ADMIN_EMAIL}"
echo "  Admin Password:  ${HERBARIUM_ADMIN_PASSWORD}"
echo ""
echo "  Tip: Save your admin password and configure SMTP/S3/ORCID in:"
echo "       ${ENV_FILE}"
echo "  Docs: docs/DAY_1_STANDUP.md"
echo "================================================================"
