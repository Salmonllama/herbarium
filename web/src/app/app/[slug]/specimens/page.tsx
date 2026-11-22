"use client";

import { use, useEffect, useState, useCallback } from "react";
import Link from "next/link";
import { CheckCircle2, Eye, Pencil, Plus, Search, Trash2 } from "lucide-react";
import { apiFetch } from "@/lib/api/client";
import type { Herbarium, PaginatedResponse, Specimen } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input, Select } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { ScientificName } from "@/components/botanical/scientific-name";

export default function WorkspaceSpecimensPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = use(params);
  const [herbarium, setHerbarium] = useState<Herbarium | null>(null);
  const [specimens, setSpecimens] = useState<Specimen[]>([]);
  const [q, setQ] = useState("");
  const [visibility, setVisibility] = useState("");
  const [status, setStatus] = useState("");
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    const params = new URLSearchParams({ per_page: "50" });
    if (q.trim()) params.set("q", q.trim());
    if (visibility) params.set("visibility", visibility);
    if (status) params.set("curation_status", status);

    try {
      const [hRes, sRes] = await Promise.all([
        apiFetch<{ data: Herbarium }>(`/api/v1/herbaria/${slug}`),
        apiFetch<PaginatedResponse<Specimen>>(`/api/v1/herbaria/${slug}/specimens?${params.toString()}`),
      ]);
      setHerbarium(hRes.data);
      setSpecimens(sRes.data);
    } finally {
      setLoading(false);
    }
  }, [slug, q, visibility, status]);

  useEffect(() => {
    const t = setTimeout(load, 200);
    return () => clearTimeout(t);
  }, [load]);

  const canCurate =
    herbarium?.current_user_role === "owner" || herbarium?.current_user_role === "curator";

  async function publish(cat: string) {
    await apiFetch(`/api/v1/herbaria/${slug}/specimens/${encodeURIComponent(cat)}/publish`, {
      method: "POST",
    });
    await load();
  }

  async function archiveSpecimen(cat: string) {
    if (!confirm(`Archive specimen ${cat}?`)) return;
    await apiFetch(`/api/v1/herbaria/${slug}/specimens/${encodeURIComponent(cat)}`, {
      method: "DELETE",
    });
    await load();
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-serif text-3xl font-semibold text-ink">Specimens</h1>
          <p className="text-sm text-ink-muted">
            Manage physical sheets, curation status, determinations, and publication state.
          </p>
        </div>
        <Link href={`/app/${slug}/specimens/new`}>
          <Button>
            <Plus className="h-4 w-4" /> New Specimen
          </Button>
        </Link>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1 max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" />
          <Input
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Search catalog ID, species, collector…"
            className="pl-9"
          />
        </div>
        <Select value={visibility} onChange={(e) => setVisibility(e.target.value)} className="sm:w-40">
          <option value="">All visibility</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="private">Private</option>
        </Select>
        <Select value={status} onChange={(e) => setStatus(e.target.value)} className="sm:w-44">
          <option value="">All curation states</option>
          <option value="unmounted">Unmounted</option>
          <option value="mounted">Mounted</option>
          <option value="verified">Verified</option>
          <option value="archived">Archived</option>
          <option value="missing">Missing</option>
        </Select>
      </div>

      <div className="overflow-x-auto rounded-lg border border-border bg-surface">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-border bg-surface-muted/70 text-xs uppercase tracking-wider text-ink-muted">
            <tr>
              <th className="px-4 py-3 font-medium">Catalog ID</th>
              <th className="px-4 py-3 font-medium">Taxon</th>
              <th className="px-4 py-3 font-medium">Collector / Date</th>
              <th className="px-4 py-3 font-medium">Storage</th>
              <th className="px-4 py-3 font-medium">Status</th>
              <th className="px-4 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {loading ? (
              <tr>
                <td colSpan={6} className="px-4 py-6 text-center text-ink-muted">
                  Loading specimens…
                </td>
              </tr>
            ) : specimens.length === 0 ? (
              <tr>
                <td colSpan={6} className="px-4 py-8 text-center text-ink-muted">
                  No specimens found.
                </td>
              </tr>
            ) : (
              specimens.map((s) => (
                <tr key={s.id} className="hover:bg-surface-muted/30">
                  <td className="px-4 py-3 font-mono text-xs font-medium whitespace-nowrap">
                    <Link
                      href={`/app/${slug}/specimens/${encodeURIComponent(s.catalog_number)}/edit`}
                      className="text-primary hover:underline"
                    >
                      {s.catalog_number}
                    </Link>
                  </td>
                  <td className="px-4 py-3">
                    <ScientificName
                      name={s.current_determination?.taxon?.scientific_name}
                      qualifier={s.current_determination?.identification_qualifier}
                    />
                  </td>
                  <td className="px-4 py-3 text-xs text-ink-muted">
                    <div>{s.recorded_by || "—"}</div>
                    <div className="font-mono text-[11px]">{s.event_date || s.verbatim_event_date || ""}</div>
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-ink-muted">
                    {[s.storage?.cabinet, s.storage?.shelf, s.storage?.folder].filter(Boolean).join(" / ") || "—"}
                  </td>
                  <td className="px-4 py-3 whitespace-nowrap space-x-1.5">
                    <Badge tone={s.visibility === "published" ? "success" : s.visibility === "draft" ? "accent" : "neutral"}>
                      {s.visibility}
                    </Badge>
                    <Badge tone="neutral">{s.curation_status}</Badge>
                  </td>
                  <td className="px-4 py-3 text-right whitespace-nowrap space-x-1">
                    {canCurate && s.visibility !== "published" && (
                      <Button size="icon" variant="ghost" onClick={() => publish(s.catalog_number)} title="Publish">
                        <CheckCircle2 className="h-3.5 w-3.5 text-success" />
                      </Button>
                    )}
                    <Link href={`/h/${slug}/specimens/${encodeURIComponent(s.catalog_number)}`}>
                      <Button size="icon" variant="ghost" title="View public page">
                        <Eye className="h-3.5 w-3.5" />
                      </Button>
                    </Link>
                    <Link href={`/app/${slug}/specimens/${encodeURIComponent(s.catalog_number)}/edit`}>
                      <Button size="icon" variant="ghost" title="Edit">
                        <Pencil className="h-3.5 w-3.5" />
                      </Button>
                    </Link>
                    {canCurate && (
                      <Button size="icon" variant="ghost" onClick={() => archiveSpecimen(s.catalog_number)} title="Archive">
                        <Trash2 className="h-3.5 w-3.5 text-danger" />
                      </Button>
                    )}
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
