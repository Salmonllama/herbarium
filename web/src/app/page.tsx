import Link from "next/link";
import { ArrowRight, BookOpen, Globe2, Leaf, MapPin, QrCode, ShieldCheck } from "lucide-react";
import { serverApiFetch } from "@/lib/api/server";
import type {
  Herbarium,
  InstanceConfig,
  PaginatedResponse,
  Specimen,
  SpecimenFacets,
  User,
} from "@/lib/api/types";
import { SiteHeader } from "@/components/layout/site-header";
import { PublicExplorer } from "@/components/botanical/public-explorer";
import { Badge } from "@/components/ui/badge";

export const dynamic = "force-dynamic";

export default async function HomePage() {
  const [instance, meRes] = await Promise.all([
    serverApiFetch<InstanceConfig>("/api/v1/instance"),
    serverApiFetch<{ user: User }>("/api/v1/me"),
  ]);

  const appName = instance?.app_name || "Herbarium";
  const currentUser = meRes?.user || null;

  // Single-herbarium self-hosted / custom-domain direct showcase mode
  if (instance?.default_herbarium_slug) {
    const slug = instance.default_herbarium_slug;
    const [herbRes, specRes, facets] = await Promise.all([
      serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${slug}`),
      serverApiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${slug}/specimens`),
      serverApiFetch<SpecimenFacets>(`/api/v1/herbaria/${slug}/specimens/facets`),
    ]);

    if (herbRes?.data && specRes) {
      const herbarium = herbRes.data;
      return (
        <div className="flex min-h-screen flex-col">
          <SiteHeader appName={appName} initialUser={currentUser} />
          <main className="flex-1">
            <section className="archival-paper border-b border-border py-10">
              <div className="mx-auto max-w-7xl px-4 sm:px-6 space-y-3">
                <div className="flex flex-wrap items-center gap-2">
                  {herbarium.institution_code && (
                    <Badge tone="primary" className="font-mono">
                      {herbarium.institution_code}
                    </Badge>
                  )}
                  <Badge tone="neutral">{herbarium.default_license}</Badge>
                </div>
                <h1 className="font-serif text-4xl font-semibold tracking-tight text-ink">
                  {herbarium.name}
                </h1>
                {herbarium.description && (
                  <p className="max-w-3xl text-base text-ink-muted leading-relaxed">
                    {herbarium.description}
                  </p>
                )}
              </div>
            </section>
            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
              <PublicExplorer
                herbarium={herbarium}
                initialSpecimens={specRes}
                initialFacets={facets}
              />
            </div>
          </main>
        </div>
      );
    }
  }

  // Multi-herbarium directory & landing portal
  const herbariaRes = await serverApiFetch<PaginatedResponse<Herbarium>>("/api/v1/herbaria?per_page=18");
  const herbaria = herbariaRes?.data || [];

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader appName={appName} initialUser={currentUser} />

      <main className="flex-1">
        {/* Archival Editorial Hero */}
        <section className="archival-paper border-b border-border">
          <div className="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20">
            <div className="max-w-3xl space-y-5">
              <p className="font-mono text-xs uppercase tracking-[0.2em] text-accent">
                Open-Source Digital Herbarium · Darwin Core Ready
              </p>
              <h1 className="font-serif text-4xl sm:text-5xl font-semibold tracking-tight text-ink leading-[1.12]">
                Preserve, determine, and share botanical collections with archival precision.
              </h1>
              <p className="text-base sm:text-lg text-ink-muted leading-relaxed max-w-2xl">
                Built for university herbaria, field researchers, students, and hobbyist collectors.
                Catalog plant taxonomy via GBIF &amp; Kew POWO, assign configurable specimen IDs,
                protect sensitive localities, and publish GBIF-ready portals.
              </p>
              <div className="flex flex-wrap items-center gap-3 pt-2">
                <Link
                  href={currentUser ? "/app" : "/auth/register"}
                  className="inline-flex h-11 items-center gap-2 rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground hover:bg-primary-hover transition-colors shadow-xs"
                >
                  {currentUser ? "Open Workspace" : "Start a Digital Herbarium"}
                  <ArrowRight className="h-4 w-4" />
                </Link>
                {herbaria[0] && (
                  <Link
                    href={`/h/${herbaria[0].slug}`}
                    className="inline-flex h-11 items-center gap-2 rounded-md border border-border bg-surface px-5 text-sm font-medium text-ink hover:bg-surface-muted transition-colors"
                  >
                    Explore {herbaria[0].name}
                  </Link>
                )}
              </div>
            </div>

            {/* Key Capabilities Strip */}
            <div className="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
              {[
                {
                  icon: BookOpen,
                  title: "Taxonomy & Determinations",
                  desc: "Herbarium-scoped taxa with GBIF/POWO lookup and full annotation history.",
                },
                {
                  icon: QrCode,
                  title: "Configurable IDs & QR Labels",
                  desc: "Atomic prefix numbering (e.g. UC-000142) and printable archival PDF labels.",
                },
                {
                  icon: ShieldCheck,
                  title: "Conservation Geoprivacy",
                  desc: "Automatic ~10 km grid obscuration and EXIF GPS stripping for rare flora.",
                },
                {
                  icon: Globe2,
                  title: "Darwin Core & Open API",
                  desc: "One-click DwC-A ZIP archives, CSV mapping wizard, and public JSON API.",
                },
              ].map(({ icon: Icon, title, desc }) => (
                <div
                  key={title}
                  className="rounded-lg border border-border bg-surface/90 p-4 space-y-1.5"
                >
                  <Icon className="h-4 w-4 text-primary" />
                  <h2 className="font-serif text-base font-semibold text-ink">{title}</h2>
                  <p className="text-xs leading-relaxed text-ink-muted">{desc}</p>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* Public Collections Directory */}
        <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6 space-y-6">
          <div className="flex items-end justify-between border-b border-border pb-4">
            <div>
              <h2 className="font-serif text-2xl sm:text-3xl font-semibold text-ink">
                Public Herbaria
              </h2>
              <p className="text-sm text-ink-muted mt-1">
                Browse openly published digital collections hosted on this instance.
              </p>
            </div>
          </div>

          {herbaria.length === 0 ? (
            <div className="rounded-lg border border-dashed border-border bg-surface p-12 text-center">
              <Leaf className="mx-auto h-8 w-8 text-ink-muted/60 mb-3" />
              <p className="font-serif text-xl text-ink">No public herbaria published yet</p>
              <p className="mt-1 text-sm text-ink-muted">
                Sign in or create an account to initialize your first digital herbarium collection.
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
              {herbaria.map((h) => (
                <Link
                  key={h.id}
                  href={`/h/${h.slug}`}
                  className="group flex flex-col rounded-lg border border-border bg-surface p-5 transition-all hover:border-primary/50 hover:shadow-md"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-mono text-xs font-medium text-primary">
                      {h.institution_code || h.id_prefix}
                    </span>
                    <Badge tone="neutral">{h.default_license}</Badge>
                  </div>
                  <h3 className="mt-2 font-serif text-xl font-semibold text-ink group-hover:text-primary transition-colors">
                    {h.name}
                  </h3>
                  <p className="mt-2 text-sm text-ink-muted line-clamp-3 flex-1">
                    {h.description || "Open-access digital herbarium catalog."}
                  </p>
                  <div className="mt-4 pt-3 border-t border-border/80 flex items-center justify-between text-xs text-ink-muted">
                    <span className="inline-flex items-center gap-1.5 font-mono">
                      <MapPin className="h-3.5 w-3.5 text-accent" />
                      {h.counts?.published_specimens ?? 0} specimens · {h.counts?.taxa ?? 0} taxa
                    </span>
                    <span className="font-medium text-primary group-hover:translate-x-0.5 transition-transform">
                      Browse →
                    </span>
                  </div>
                </Link>
              ))}
            </div>
          )}
        </section>
      </main>

      <footer className="border-t border-border bg-surface py-6 text-xs text-ink-muted">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 sm:px-6">
          <span>
            Powered by <strong className="font-serif text-ink">Herbarium</strong> · Open-Source
            Digital Herbarium Software (AGPL-3.0)
          </span>
          <a href="/docs/api" className="hover:text-ink underline">
            OpenAPI Documentation
          </a>
        </div>
      </footer>
    </div>
  );
}
