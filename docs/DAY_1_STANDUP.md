# Day-1 Stand-Up Guide — Self-Hosting Herbarium

This guide takes you from a fresh Linux server (or local machine) to a live, HTTPS-secured **Herbarium** instance in under 10 minutes using `./install.sh`.

---

## 1. Prerequisites

| Requirement | Minimum | Recommended (University / Institutional) |
|---|---|---|
| **OS** | Ubuntu 22.04+ / Debian 12+ / macOS | Ubuntu 24.04 LTS |
| **Runtime** | Docker Engine 24+ & Docker Compose v2 | Docker Engine 26+ |
| **CPU / RAM** | 2 vCPU · 2 GB RAM | 4 vCPU · 8 GB RAM |
| **Storage** | 20 GB SSD | 100 GB+ SSD or S3 / Cloudflare R2 / MinIO |
| **Ports** | `80` & `443` (or custom port for local eval) | `80` & `443` open for Let's Encrypt TLS |

If Docker is not yet installed on Ubuntu/Debian:
```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
# Log out and back in so docker group membership takes effect
```

---

## 2. DNS Setup (Production Domains)

Point an `A` (and optional `AAAA`) DNS record for your domain to your server's public IP before running the installer:

```text
herbarium.your-university.edu   A   203.0.113.42
```

Caddy will automatically provision and renew Let's Encrypt HTTPS certificates on first startup.

---

## 3. Day-1 Installation (`./install.sh`)

```bash
git clone https://github.com/Salmonllama/herbarium.git
cd herbarium
chmod +x install.sh scripts/*.sh
./install.sh
```

The interactive installer walks you through:
1. **Domain & Ports:** Enter your public domain (for automatic HTTPS) or `localhost` (for local HTTP evaluation).
2. **Registration Mode:** Choose `invite_only` (recommended for universities and personal herbaria) or `open` (multi-user community / hosted SaaS).
3. **First Herbarium & Prefix:** Name your collection (e.g., *Oregon State Vascular Flora*), pick your Catalog ID prefix (e.g., `OSC` → `OSC-000001`), and choose whether to pin it directly to `/`.
4. **Initial Administrator:** Provide your email/name (password can be auto-generated securely).
5. **Optional Demo Collection:** Seed 5 Pacific Northwest plant taxa and 6 specimens (including a sensitive orchid demonstrating automatic ~10 km geoprivacy obscuration).
6. **Storage & Search:** Choose local Docker volume or S3-compatible storage, and built-in PostgreSQL `pg_trgm` search (default) or optional Meilisearch for 500,000+ specimen collections.

### Non-Interactive / Automated Provisioning (CI, Terraform, Ansible)

```bash
HERBARIUM_DOMAIN="herbarium.example.edu" \
HERBARIUM_ADMIN_EMAIL="curator@example.edu" \
HERBARIUM_ADMIN_PASSWORD="ReplaceWithStrongPassword!" \
HERBARIUM_FIRST_NAME="University Botanical Collection" \
HERBARIUM_FIRST_PREFIX="UBC" \
HERBARIUM_SEED_DEMO=false \
./install.sh --non-interactive
```

---

## 4. Day-1 Post-Install Verification

Once `./install.sh` finishes, verify:
1. **API Health:** `curl https://herbarium.example.edu/api/v1/health` → `{"status":"ok","checks":{"database":true,"redis":true}}`
2. **Public Portal:** Open `https://herbarium.example.edu` in your browser.
3. **Curator Sign-In:** Navigate to `/auth/login` with your administrator credentials and open `/app`.
4. **OpenAPI Documentation:** Browse `/docs/api` for interactive REST API docs.

---

## 5. Optional Production Configuration (`deploy/.env`)

Edit `deploy/.env` and restart with `docker compose -f deploy/docker-compose.yml --env-file deploy/.env up -d`:

### S3 / Cloudflare R2 / MinIO Object Storage
```dotenv
HERBARIUM_MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=herbarium-media
AWS_ENDPOINT=https://<account>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

### ORCID & Institutional OIDC SSO
```dotenv
ORCID_CLIENT_ID=APP-...
ORCID_CLIENT_SECRET=...
ORCID_REDIRECT_URI="${APP_URL}/auth/orcid/callback"

OIDC_ENABLED=true
OIDC_LABEL="University SSO"
OIDC_ISSUER=https://sso.your-university.edu/realms/campus
OIDC_CLIENT_ID=herbarium
OIDC_CLIENT_SECRET=...
OIDC_REDIRECT_URI="${APP_URL}/auth/oidc/callback"
```

### Enabling Optional Meilisearch Profile (500k+ Specimens)
Set `SCOUT_DRIVER=meilisearch` and `MEILISEARCH_KEY=<secret>` in `deploy/.env`, then run:
```bash
docker compose -f deploy/docker-compose.yml --env-file deploy/.env --profile search up -d
docker compose -f deploy/docker-compose.yml --env-file deploy/.env exec api php artisan scout:import "App\Models\Specimen"
```

---

## 6. Backups & Upgrades

- **Daily Backup** (PostgreSQL + PostGIS custom dump + media archive into `backups/`):
  ```bash
  ./scripts/backup.sh
  ```
- **Upgrade to Latest Release:**
  ```bash
  ./scripts/upgrade.sh
  ```

---

## 7. Local Developer Stand-Up (Hot Reload)

```bash
# 1. Start PostgreSQL + PostGIS and Redis
docker compose -f deploy/docker-compose.dev.yml up -d

# 2. Configure & run Laravel API (port 8000)
cd api
composer install
cp .env.example .env && php artisan key:generate
# Set DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_DATABASE=herbarium, DB_USERNAME=herbarium, DB_PASSWORD=herbarium
php artisan migrate
php artisan herbarium:bootstrap --email=curator@herbarium.local --password=HerbariumDemo123! --seed-demo
php artisan serve --port=8000

# 3. Run Next.js Frontend (port 3000 — automatically proxies /api, /sanctum, /auth to :8000)
cd ../web
npm install
npm run dev
```
