# Herbarium

**Open-source digital herbarium software for universities, research institutions, botanical students, and hobbyists.**

Catalog plant taxonomy and descriptions, manage physical & digital voucher specimens with configurable ID prefixes, preserve formal determination history, protect endangered plant locations, print archival QR sheet labels, and publish shareable Darwin Core–compliant online portals.

- **License:** GNU AGPL-3.0 (100% full-featured open-source core; hosted cloud & institutional support offerings build cleanly on the core quota hooks).
- **Stack:** Decoupled **Laravel 12 REST API** (PHP 8.3+, Sanctum, Scout, Scramble OpenAPI 3.1) + **Next.js App Router** (React, TypeScript, Tailwind CSS) + **PostgreSQL 16 / PostGIS / `pg_trgm`** + **Redis** + **Caddy (Auto-HTTPS)**.

---

## Key Features (v1)

- **Multi-Herbarium Workspaces:** Run one personal herbarium pinned directly to `/`, multiple university department collections, or a multi-tenant hosted deployment from the same codebase.
- **Herbarium-Scoped Taxonomy & Backbone Lookup:** Store full Linnaean hierarchies, common names, and custom botanical descriptions per herbarium, with one-click autocomplete from **GBIF Backbone** and **Kew POWO / IPNI** (plus full offline manual entry).
- **Configurable Specimen IDs & Determination History:** Atomic per-herbarium catalog numbering (e.g., `OSC-000042` with custom prefix, separator, and padding) alongside manual overrides for legacy sheets, separate field/collector numbers, physical cabinet/shelf/folder tracking, and versioned specialist re-identifications (`cf.`, `aff.`, `indet.`).
- **Conservation Geoprivacy & Interactive Maps:** Automatic ~10 km deterministic grid obscuration for sensitive/threatened taxa or private land, enforced at the PostGIS SQL query layer to prevent bounding-box probing, rendered on zero-API-key **Leaflet / OpenStreetMap** views.
- **EXIF-Assisted Imaging & Privacy Stripping:** Upload pressed sheet scans and field photos with immediate EXIF capture-date & GPS autofill prompts; background workers generate high-resolution EXIF-stripped WebP derivatives for smooth pan/zoom inspection.
- **Mobile Field Capture & Student Draft Workflow:** Role-based access (`Owner`, `Curator`, `Contributor/Student`, `Viewer`) with a draft review queue and phone-friendly one-tap browser GPS capture.
- **Archival Printable QR Labels & GBIF Exports:** Generate print-ready PDF herbarium sheet labels (US Letter 4-up, A4 4-up, 4"×3" single) with QR codes linking to specimen permalinks; migrate spreadsheets via the **CSV Column-Mapping Wizard**; export standards-compliant **Darwin Core Archive (DwC-A)** ZIP bundles and query the documented **OpenAPI 3.1 REST API**.
- **Institutional Identity:** Email/password out of the box plus optional **ORCID** and generic **OpenID Connect (OIDC)** university SSO.

---

## Day-1 Stand-Up (Self-Hosting in Minutes)

Requires **Docker Engine** and **Docker Compose v2** on any Linux server or local machine:

```bash
git clone https://github.com/Salmonllama/herbarium.git
cd herbarium
chmod +x install.sh scripts/*.sh
./install.sh
```

`install.sh` guides you through domain/HTTPS setup (automatic Let's Encrypt via Caddy, or `localhost` for local evaluation), generates cryptographic secrets, starts PostgreSQL+PostGIS, Redis, Laravel API, Queue Worker, Scheduler, and Next.js, runs migrations, and bootstraps your administrator account and first herbarium.

See **[docs/DAY_1_STANDUP.md](docs/DAY_1_STANDUP.md)** for full production instructions (DNS, S3/R2 storage, ORCID/OIDC SSO, optional Meilisearch profile, backups, and upgrades).

---

## Local Development Stand-Up

```bash
# 1. Start PostgreSQL + PostGIS and Redis
docker compose -f deploy/docker-compose.dev.yml up -d

# 2. Boot Laravel API (http://127.0.0.1:8000)
cd api
composer install
cp .env.example .env && php artisan key:generate
# Configure DB_CONNECTION=pgsql (herbarium / herbarium @ 127.0.0.1:5432)
php artisan migrate
php artisan herbarium:bootstrap --email=curator@herbarium.local --password=HerbariumDemo123! --seed-demo
php artisan serve --port=8000

# 3. Boot Next.js Frontend (http://localhost:3000)
cd ../web
npm install
npm run dev
```

### Running Tests & Regenerating OpenAPI Types

```bash
# Backend feature & unit tests (runs against real PostgreSQL + PostGIS)
cd api && php artisan test

# Export OpenAPI 3.1 spec & regenerate Next.js TypeScript schema
cd api && php artisan scramble:export --path=openapi.json
cd web && npx openapi-typescript ../api/openapi.json -o src/lib/api/schema.d.ts

# Frontend lint & production build
cd web && npm run lint && npm run build
```

---

## Documentation

- **[Day-1 Stand-Up & Operations Guide](docs/DAY_1_STANDUP.md)**
- **[Product & Technical Architecture Specification](docs/ARCHITECTURE_SPEC.md)**
- **[Darwin Core (DwC-A) & GBIF Mapping Reference](docs/DARWIN_CORE_MAPPING.md)**

## License

Herbarium is free, open-source software licensed under the **GNU Affero General Public License v3.0 (AGPL-3.0)**. See [LICENSE](LICENSE) for full terms.
