import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Settings2 } from "lucide-react";
import { serverApiFetch } from "@/lib/api/server";
import type { Herbarium, PaginatedResponse, Specimen, SpecimenFacets, User } from "@/lib/api/types";
import { SiteHeader } from "@/components/layout/site-header";
import { PublicExplorer } from "@/components/botanical/public-explorer";
import { Badge } from "@/components/ui/badge";

export const dynamic = "force-dynamic";

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const res = await serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encodeURIComponent(slug)}`);
  if (!res?.data) {
    return { title: "Herbarium Not Found" };
  }
  return {
    title: res.data.name,
    description: res.data.description || `Digital herbarium specimen catalog for ${res.data.name}.`,
  };
}

export default async function PublicHerbariumPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const encoded = encodeURIComponent(slug);

  const [herbRes, specRes, facets, meRes] = await Promise.all([
    serverApiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${encoded}`),
    serverApiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${encoded}/specimens`),
    serverApiFetch<SpecimenFacets>(`/api/v1/herbaria/${encoded}/specimens/facets`),
    serverApiFetch<{ user: User }>("/api/v1/me"),
  ]);

  if (!herbRes?.data) {
    notFound();
  }

  const herbarium = herbRes.data;
  const isMember = herbarium.current_user_role !== null;

  return (
    <div className="flex min-h-screen flex-col">
      <SiteHeader initialUser={meRes?.user || null} />

      <main className="flex-1">
        <section className="archival-paper border-b border-border py-10">
          <div className="mx-auto max-w-7xl px-4 sm:px-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="space-y-2.5 max-w-3xl">
              <div className="flex flex-wrap items-center gap-2">
                {herbarium.institution_code && (
                  <Badge tone="primary" className="font-mono">
                    {herbarium.institution_code}
                  </Badge>
                )}
                <Badge tone="neutral">{herbarium.default_license}</Badge>
                {herbarium.visibility !== "public" && (
                  <Badge tone="accent" className="capitalize">
                    {herbarium.visibility}
                  </Badge>
                )}
              </div>
              <h1 className="font-serif text-3xl sm:text-4xl font-semibold tracking-tight text-ink">
                {herbarium.name}
              </h1>
              {herbarium.description && (
                <p className="text-sm sm:text-base text-ink-muted leading-relaxed">
                  {herbarium.description}
                </p>
              )}
            </div>

            <div className="flex flex-wrap items-center gap-4">
              <div className="flex items-center gap-5 rounded-lg border border-border bg-surface px-4 py-2.5 text-center">
                <div>
                  <div className="font-mono text-lg font-semibold text-ink">
                    {herbarium.counts?.published_specimens ?? 0}
                  </div>
                  <div className="text-[11px] uppercase tracking-wider text-ink-muted">Specimens</div>
                </div>
                <div className="h-8 w-px bg-border" />
                <div>
                  <div className="font-mono text-lg font-semibold text-ink">
                    {herbarium.counts?.taxa ?? 0}
                  </div>
                  <div className="text-[11px] uppercase tracking-wider text-ink-muted">Taxa</div>
                </div>
              </div>

              {isMember && (
                <Link
                  href={`/app/${herbarium.slug}`}
                  className="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary-hover transition-colors"
                >
                  <Settings2 className="h-4 w-4" />
                  Manage Collection
                </Link>
              )}
            </div>
          </div>
        </section>

        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6">
          <PublicExplorer
            herbarium={herbarium}
            initialSpecimens={specRes || { data: [] }}
            initialFacets={facets}
          />
        </div>
      </main>
    </div>
  );
}
