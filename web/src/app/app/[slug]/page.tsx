"use client";

import { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { CheckCircle2, FileSpreadsheet, Plus, QrCode, Smartphone } from "lucide-react";
import { apiFetch } from "@/lib/api/client";
import type { Herbarium, PaginatedResponse, Specimen } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScientificName } from "@/components/botanical/scientific-name";

export default function HerbariumOverviewPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = use(params);
  const [herbarium, setHerbarium] = useState<Herbarium | null>(null);
  const [drafts, setDrafts] = useState<Specimen[]>([]);
  const [recent, setRecent] = useState<Specimen[]>([]);

  const load = useCallback(async () => {
    const [hRes, dRes, rRes] = await Promise.all([
      apiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${slug}`),
      apiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${slug}/specimens?visibility=draft&per_page=10`),
      apiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${slug}/specimens?per_page=6`),
    ]);
    setHerbarium(hRes.data);
    setDrafts(dRes.data);
    setRecent(rRes.data);
  }, [slug]);

  useEffect(() => {
    let active = true;
    Promise.all([
      apiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${slug}`),
      apiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${slug}/specimens?visibility=draft&per_page=10`),
      apiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${slug}/specimens?per_page=6`),
    ])
      .then(([hRes, dRes, rRes]) => {
        if (!active) return;
        setHerbarium(hRes.data);
        setDrafts(dRes.data);
        setRecent(rRes.data);
      })
      .catch(() => {});
    return () => {
      active = false;
    };
  }, [slug]);

  const canCurate =
    herbarium?.current_user_role === "owner" || herbarium?.current_user_role === "curator";

  async function publishDraft(catalogNumber: string) {
    await apiFetch(
      `/api/v1/herbaria/${slug}/specimens/${encodeURIComponent(catalogNumber)}/publish`,
      { method: "POST" },
    );
    await load();
  }

  if (!herbarium) {
    return <p className="text-sm text-ink-muted">Loading collection overview…</p>;
  }

  return (
    <div className="space-y-8">
      {/* Stats Row */}
      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div className="rounded-lg border border-border bg-surface p-5">
          <span className="text-xs uppercase tracking-wider text-ink-muted">Total Specimens</span>
          <div className="mt-1 font-mono text-3xl font-semibold text-ink">
            {herbarium.counts?.specimens ?? 0}
          </div>
        </div>
        <div className="rounded-lg border border-border bg-surface p-5">
          <span className="text-xs uppercase tracking-wider text-ink-muted">Published</span>
          <div className="mt-1 font-mono text-3xl font-semibold text-primary">
            {herbarium.counts?.published_specimens ?? 0}
          </div>
        </div>
        <div className="rounded-lg border border-border bg-surface p-5">
          <span className="text-xs uppercase tracking-wider text-ink-muted">Cataloged Taxa</span>
          <div className="mt-1 font-mono text-3xl font-semibold text-ink">
            {herbarium.counts?.taxa ?? 0}
          </div>
        </div>
        <div className="rounded-lg border border-border bg-surface p-5">
          <span className="text-xs uppercase tracking-wider text-ink-muted">Next Auto ID</span>
          <div className="mt-2 font-mono text-lg font-semibold text-accent truncate">
            {herbarium.next_catalog_number_preview || `${herbarium.id_prefix}-000001`}
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Link
          href={`/app/${slug}/specimens/new`}
          className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4 hover:border-primary/50 transition-colors"
        >
          <Plus className="h-5 w-5 text-primary shrink-0" />
          <div>
            <div className="text-sm font-medium text-ink">Catalog Specimen</div>
            <div className="text-xs text-ink-muted">Assign ID &amp; record metadata</div>
          </div>
        </Link>
        <Link
          href={`/app/${slug}/specimens/new`}
          className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4 hover:border-primary/50 transition-colors"
        >
          <Smartphone className="h-5 w-5 text-accent shrink-0" />
          <div>
            <div className="text-sm font-medium text-ink">Field Capture</div>
            <div className="text-xs text-ink-muted">Phone GPS &amp; EXIF photos</div>
          </div>
        </Link>
        <Link
          href={`/app/${slug}/labels`}
          className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4 hover:border-primary/50 transition-colors"
        >
          <QrCode className="h-5 w-5 text-primary shrink-0" />
          <div>
            <div className="text-sm font-medium text-ink">Print QR Labels</div>
            <div className="text-xs text-ink-muted">Archival PDF sheet labels</div>
          </div>
        </Link>
        {canCurate && (
          <Link
            href={`/app/${slug}/import-export`}
            className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4 hover:border-primary/50 transition-colors"
          >
            <FileSpreadsheet className="h-5 w-5 text-accent shrink-0" />
            <div>
              <div className="text-sm font-medium text-ink">CSV &amp; DwC-A</div>
              <div className="text-xs text-ink-muted">Bulk import or GBIF export</div>
            </div>
          </Link>
        )}
      </div>

      {/* Draft Review Queue */}
      <section className="rounded-lg border border-border bg-surface p-6 space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="font-serif text-xl font-semibold text-ink">
              Draft Review Queue ({drafts.length})
            </h2>
            <p className="text-xs text-ink-muted">
              Student &amp; contributor submissions awaiting curator review and publication.
            </p>
          </div>
          <Link href={`/app/${slug}/specimens`} className="text-xs font-medium text-primary hover:underline">
            View all specimens →
          </Link>
        </div>

        {drafts.length === 0 ? (
          <p className="text-sm text-ink-muted py-4">No drafts awaiting review.</p>
        ) : (
          <ul className="divide-y divide-border">
            {drafts.map((d) => (
              <li key={d.id} className="py-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <Link
                      href={`/app/${slug}/specimens/${encodeURIComponent(d.catalog_number)}/edit`}
                      className="font-mono text-xs font-semibold text-primary hover:underline"
                    >
                      {d.catalog_number}
                    </Link>
                    <ScientificName name={d.current_determination?.taxon?.scientific_name} />
                    <Badge tone="accent">Draft</Badge>
                  </div>
                  <div className="text-xs text-ink-muted mt-0.5">
                    Leg. {d.recorded_by || "Unknown"} · {d.event_date || d.verbatim_event_date || "s.d."}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Link href={`/app/${slug}/specimens/${encodeURIComponent(d.catalog_number)}/edit`}>
                    <Button size="sm" variant="outline">
                      Review
                    </Button>
                  </Link>
                  {canCurate && (
                    <Button size="sm" onClick={() => publishDraft(d.catalog_number)}>
                      <CheckCircle2 className="h-3.5 w-3.5" /> Publish
                    </Button>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Recent Activity */}
      {recent.length > 0 && (
        <section className="rounded-lg border border-border bg-surface p-6 space-y-3">
          <h2 className="font-serif text-xl font-semibold text-ink">Recently Cataloged</h2>
          <div className="divide-y divide-border">
            {recent.map((s) => (
              <div key={s.id} className="py-2.5 flex items-center justify-between gap-3 text-sm">
                <div className="flex items-center gap-3 min-w-0">
                  <span className="font-mono text-xs text-primary shrink-0">{s.catalog_number}</span>
                  <span className="truncate">
                    <ScientificName name={s.current_determination?.taxon?.scientific_name} />
                  </span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                  <Badge tone={s.visibility === "published" ? "success" : "neutral"}>
                    {s.visibility}
                  </Badge>
                  <Link
                    href={`/app/${slug}/specimens/${encodeURIComponent(s.catalog_number)}/edit`}
                    className="text-xs text-ink-muted hover:text-ink"
                  >
                    Edit
                  </Link>
                </div>
              </div>
            ))}
          </div>
        </section>
      )}
    </div>
  );
}
