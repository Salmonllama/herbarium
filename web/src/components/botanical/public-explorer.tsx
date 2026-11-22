"use client";

import { useEffect, useState, useCallback } from "react";
import Link from "next/link";
import {
  Download,
  FileArchive,
  FolderTree,
  Grid2X2,
  List,
  Map as MapIcon,
  Search,
  ShieldAlert,
  ChevronRight,
  ChevronDown,
  Leaf,
  X,
} from "lucide-react";
import type {
  GeoJsonCollection,
  Herbarium,
  PaginatedResponse,
  Specimen,
  SpecimenFacets,
  TaxonomicTreeFamily,
} from "@/lib/api/types";
import { apiFetch } from "@/lib/api/client";
import { Input, Select } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { ScientificName } from "./scientific-name";
import { SpecimenMap } from "./specimen-map";

type ViewMode = "grid" | "table" | "tree" | "map";

export function PublicExplorer({
  herbarium,
  initialSpecimens,
  initialFacets,
}: {
  herbarium: Herbarium;
  initialSpecimens: PaginatedResponse<Specimen>;
  initialFacets: SpecimenFacets | null;
}) {
  const [view, setView] = useState<ViewMode>("grid");
  const [q, setQ] = useState("");
  const [family, setFamily] = useState("");
  const [genus, setGenus] = useState("");
  const [country, setCountry] = useState("");
  const [typeStatus, setTypeStatus] = useState("");
  const [sort, setSort] = useState("created_desc");
  const [page, setPage] = useState(1);

  const [specimens, setSpecimens] = useState<Specimen[]>(initialSpecimens.data || []);
  const [meta, setMeta] = useState(initialSpecimens.meta);
  const [loading, setLoading] = useState(false);

  const [tree, setTree] = useState<TaxonomicTreeFamily[] | null>(null);
  const [openFamilies, setOpenFamilies] = useState<Record<string, boolean>>({});
  const [mapData, setMapData] = useState<GeoJsonCollection | null>(null);

  const buildQueryString = useCallback(
    (includePage = true) => {
      const params = new URLSearchParams();
      if (q.trim()) params.set("q", q.trim());
      if (family) params.set("family", family);
      if (genus) params.set("genus", genus);
      if (country) params.set("country", country);
      if (typeStatus) params.set("type_status", typeStatus);
      if (sort) params.set("sort", sort);
      if (includePage && page > 1) params.set("page", String(page));
      return params.toString();
    },
    [q, family, genus, country, typeStatus, sort, page],
  );

  const fetchSpecimens = useCallback(async () => {
    setLoading(true);
    try {
      const qs = buildQueryString(true);
      const res = await apiFetch<PaginatedResponse<Specimen>>(
        `/api/v1/herbaria/${herbarium.slug}/specimens${qs ? `?${qs}` : ""}`,
      );
      setSpecimens(res.data);
      setMeta(res.meta);
    } catch {
      // keep existing state on transient error
    } finally {
      setLoading(false);
    }
  }, [herbarium.slug, buildQueryString]);

  useEffect(() => {
    const timer = setTimeout(() => {
      fetchSpecimens();
    }, 220);
    return () => clearTimeout(timer);
  }, [fetchSpecimens]);

  useEffect(() => {
    if (view === "tree" && tree === null) {
      apiFetch<{ data: TaxonomicTreeFamily[] }>(`/api/v1/herbaria/${herbarium.slug}/taxonomy/tree`)
        .then((res) => {
          setTree(res.data);
          const initialOpen: Record<string, boolean> = {};
          res.data.slice(0, 5).forEach((f) => {
            initialOpen[f.family] = true;
          });
          setOpenFamilies(initialOpen);
        })
        .catch(() => setTree([]));
    }
  }, [view, tree, herbarium.slug]);

  useEffect(() => {
    if (view === "map") {
      const qs = buildQueryString(false);
      apiFetch<GeoJsonCollection>(
        `/api/v1/herbaria/${herbarium.slug}/map/points${qs ? `?${qs}` : ""}`,
      )
        .then((res) => setMapData(res))
        .catch(() => setMapData({ type: "FeatureCollection", count: 0, features: [] }));
    }
  }, [view, herbarium.slug, buildQueryString]);

  const hasFilters = Boolean(q || family || genus || country || typeStatus);
  const exportQs = buildQueryString(false);

  function clearFilters() {
    setQ("");
    setFamily("");
    setGenus("");
    setCountry("");
    setTypeStatus("");
    setPage(1);
  }

  return (
    <div className="space-y-6">
      {/* Control Bar */}
      <div className="rounded-lg border border-border bg-surface p-4 shadow-2xs space-y-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="relative flex-1 max-w-xl">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" />
            <Input
              value={q}
              onChange={(e) => {
                setQ(e.target.value);
                setPage(1);
              }}
              placeholder="Search species, family, catalog ID, collector, or region…"
              className="pl-9"
              aria-label="Search specimens"
            />
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <div className="inline-flex rounded-md border border-border bg-surface-muted p-0.5">
              {(
                [
                  { id: "grid", label: "Sheets", icon: Grid2X2 },
                  { id: "table", label: "Table", icon: List },
                  { id: "tree", label: "Taxonomy", icon: FolderTree },
                  { id: "map", label: "Map", icon: MapIcon },
                ] as const
              ).map(({ id, label, icon: Icon }) => (
                <button
                  key={id}
                  type="button"
                  onClick={() => setView(id)}
                  className={`inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer ${
                    view === id
                      ? "bg-surface text-ink shadow-2xs"
                      : "text-ink-muted hover:text-ink"
                  }`}
                >
                  <Icon className="h-3.5 w-3.5" />
                  <span>{label}</span>
                </button>
              ))}
            </div>

            <a
              href={`/api/v1/herbaria/${herbarium.slug}/exports/csv${exportQs ? `?${exportQs}` : ""}`}
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-surface px-3 text-xs font-medium text-ink hover:bg-surface-muted"
            >
              <Download className="h-3.5 w-3.5" />
              CSV
            </a>
            <a
              href={`/api/v1/herbaria/${herbarium.slug}/exports/dwca${exportQs ? `?${exportQs}` : ""}`}
              className="inline-flex h-8 items-center gap-1.5 rounded-md border border-border bg-surface px-3 text-xs font-medium text-ink hover:bg-surface-muted"
              title="Download GBIF-ready Darwin Core Archive (DwC-A)"
            >
              <FileArchive className="h-3.5 w-3.5" />
              DwC-A
            </a>
          </div>
        </div>

        {/* Faceted Filters */}
        <div className="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-5">
          <Select
            value={family}
            onChange={(e) => {
              setFamily(e.target.value);
              setPage(1);
            }}
            aria-label="Filter by Family"
          >
            <option value="">All Families</option>
            {initialFacets?.families.map((f) => (
              <option key={f.value} value={f.value}>
                {f.value} ({f.count})
              </option>
            ))}
          </Select>

          <Select
            value={genus}
            onChange={(e) => {
              setGenus(e.target.value);
              setPage(1);
            }}
            aria-label="Filter by Genus"
          >
            <option value="">All Genera</option>
            {initialFacets?.genera.map((g) => (
              <option key={g.value} value={g.value}>
                {g.value} ({g.count})
              </option>
            ))}
          </Select>

          <Select
            value={country}
            onChange={(e) => {
              setCountry(e.target.value);
              setPage(1);
            }}
            aria-label="Filter by Country"
          >
            <option value="">All Countries</option>
            {initialFacets?.countries.map((c) => (
              <option key={c.value} value={c.value}>
                {c.value} ({c.count})
              </option>
            ))}
          </Select>

          <Select
            value={typeStatus}
            onChange={(e) => {
              setTypeStatus(e.target.value);
              setPage(1);
            }}
            aria-label="Filter by Type Status"
          >
            <option value="">Any Type Status</option>
            {initialFacets?.type_statuses.map((t) => (
              <option key={t.value} value={t.value}>
                {t.value} ({t.count})
              </option>
            ))}
          </Select>

          <Select
            value={sort}
            onChange={(e) => setSort(e.target.value)}
            aria-label="Sort specimens"
          >
            <option value="created_desc">Recently Cataloged</option>
            <option value="catalog_asc">Catalog ID (A–Z)</option>
            <option value="catalog_desc">Catalog ID (Z–A)</option>
            <option value="event_date_desc">Collection Date (Newest)</option>
            <option value="event_date_asc">Collection Date (Oldest)</option>
          </Select>
        </div>

        {hasFilters && (
          <div className="flex items-center justify-between pt-1 text-xs text-ink-muted">
            <span>
              Showing {meta?.total ?? specimens.length} matching specimen
              {(meta?.total ?? specimens.length) === 1 ? "" : "s"}
            </span>
            <button
              type="button"
              onClick={clearFilters}
              className="inline-flex items-center gap-1 text-primary hover:underline cursor-pointer"
            >
              <X className="h-3 w-3" /> Clear filters
            </button>
          </div>
        )}
      </div>

      {/* Active View */}
      {view === "tree" ? (
        <div className="rounded-lg border border-border bg-surface p-5">
          <h2 className="font-serif text-xl font-semibold mb-4">Taxonomic Browser</h2>
          {tree === null ? (
            <p className="text-sm text-ink-muted">Loading taxonomic hierarchy…</p>
          ) : tree.length === 0 ? (
            <p className="text-sm text-ink-muted">No taxa cataloged in this herbarium yet.</p>
          ) : (
            <div className="divide-y divide-border">
              {tree.map((fam) => {
                const isOpen = !!openFamilies[fam.family];
                return (
                  <div key={fam.family} className="py-3">
                    <button
                      type="button"
                      onClick={() =>
                        setOpenFamilies((prev) => ({ ...prev, [fam.family]: !prev[fam.family] }))
                      }
                      className="flex w-full items-center justify-between text-left cursor-pointer group"
                    >
                      <span className="flex items-center gap-2">
                        {isOpen ? (
                          <ChevronDown className="h-4 w-4 text-ink-muted" />
                        ) : (
                          <ChevronRight className="h-4 w-4 text-ink-muted" />
                        )}
                        <span className="text-xs font-semibold uppercase tracking-widest text-accent">
                          {fam.family}
                        </span>
                      </span>
                      <span className="font-mono text-xs text-ink-muted">
                        {fam.taxa_count} taxa · {fam.specimen_count} specimens
                      </span>
                    </button>

                    {isOpen && (
                      <div className="mt-3 ml-6 space-y-3">
                        {fam.genera.map((gen) => (
                          <div key={gen.genus}>
                            <div className="flex items-center justify-between text-sm font-medium text-ink">
                              <span className="font-serif italic">{gen.genus}</span>
                              <button
                                type="button"
                                onClick={() => {
                                  setFamily(fam.family === "Unassigned Family" ? "" : fam.family);
                                  setGenus(gen.genus === "Unassigned Genus" ? "" : gen.genus);
                                  setView("grid");
                                }}
                                className="text-xs text-primary hover:underline cursor-pointer"
                              >
                                Filter ({gen.specimen_count})
                              </button>
                            </div>
                            <ul className="mt-1.5 ml-4 space-y-1 border-l border-border pl-3">
                              {gen.taxa.map((t) => (
                                <li key={t.id} className="flex items-center justify-between text-sm">
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setQ(t.scientific_name);
                                      setView("grid");
                                    }}
                                    className="text-left hover:text-primary cursor-pointer"
                                  >
                                    <ScientificName
                                      name={t.scientific_name}
                                      authorship={t.scientific_name_authorship}
                                    />
                                  </button>
                                  <span className="font-mono text-xs text-ink-muted">
                                    {t.specimen_count}
                                  </span>
                                </li>
                              ))}
                            </ul>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      ) : view === "map" ? (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-ink-muted">
            <span>
              Displaying {mapData?.count ?? 0} georeferenced specimens (OpenStreetMap).
            </span>
            <span className="inline-flex items-center gap-3">
              <span className="inline-flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-primary inline-block" /> Exact locality
              </span>
              <span className="inline-flex items-center gap-1.5">
                <span className="h-2.5 w-2.5 rounded-full bg-accent inline-block" /> Obscured (~10 km grid)
              </span>
            </span>
          </div>
          <SpecimenMap
            features={mapData?.features || []}
            herbariumSlug={herbarium.slug}
            heightClass="h-[540px]"
          />
        </div>
      ) : specimens.length === 0 ? (
        <div className="rounded-lg border border-dashed border-border bg-surface p-12 text-center">
          <Leaf className="mx-auto h-8 w-8 text-ink-muted/60 mb-3" />
          <p className="font-serif text-xl text-ink">No matching specimens</p>
          <p className="mt-1 text-sm text-ink-muted">
            {loading ? "Searching collection…" : "Try adjusting your search query or clearing active filters."}
          </p>
        </div>
      ) : view === "grid" ? (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {specimens.map((spec) => {
            const taxon = spec.current_determination?.taxon;
            const thumb = spec.media?.[0]?.thumb_url || spec.media?.[0]?.public_url;
            const href = `/h/${herbarium.slug}/specimens/${encodeURIComponent(spec.catalog_number)}`;
            const geoLine = [spec.location.state_province, spec.location.country]
              .filter(Boolean)
              .join(", ");

            return (
              <Link
                key={spec.id}
                href={href}
                className="group flex flex-col overflow-hidden rounded-lg border border-border bg-surface transition-all hover:border-primary/50 hover:shadow-md"
              >
                <div className="relative aspect-[4/3] w-full overflow-hidden bg-surface-muted">
                  {thumb ? (
                    // eslint-disable-next-line @next/next/no-img-element
                    <img
                      src={thumb}
                      alt={taxon?.scientific_name || spec.catalog_number}
                      className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                    />
                  ) : (
                    <div className="flex h-full w-full flex-col items-center justify-center p-6 text-center archival-paper">
                      <span className="font-mono text-[11px] uppercase tracking-widest text-ink-muted">
                        {taxon?.family || "Herbarium Voucher"}
                      </span>
                      <span className="mt-2 font-serif italic text-xl text-ink/80">
                        {taxon?.scientific_name || "Indeterminata"}
                      </span>
                    </div>
                  )}
                  <div className="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                    <span className="rounded bg-surface/95 px-2 py-0.5 font-mono text-[11px] font-medium text-ink shadow-2xs border border-border/80">
                      {spec.catalog_number}
                    </span>
                    {spec.type_status && <Badge tone="danger">{spec.type_status}</Badge>}
                  </div>
                  {spec.location.coordinates_obscured && (
                    <span
                      title="Sensitive location obscured"
                      className="absolute top-2.5 right-2.5 rounded bg-surface/95 p-1 text-accent border border-border/80"
                    >
                      <ShieldAlert className="h-3.5 w-3.5" />
                    </span>
                  )}
                </div>

                <div className="flex flex-1 flex-col p-4">
                  <div className="text-[11px] font-semibold uppercase tracking-wider text-accent">
                    {taxon?.family || "Unassigned Family"}
                  </div>
                  <h3 className="mt-0.5 text-lg leading-snug group-hover:text-primary transition-colors">
                    <ScientificName
                      name={taxon?.scientific_name}
                      authorship={taxon?.scientific_name_authorship}
                      qualifier={spec.current_determination?.identification_qualifier}
                    />
                  </h3>
                  {taxon?.common_names?.[0]?.name && (
                    <p className="text-xs text-ink-muted mt-0.5">{taxon.common_names[0].name}</p>
                  )}

                  <div className="mt-auto pt-3 border-t border-border/70 flex items-center justify-between text-xs text-ink-muted gap-2">
                    <span className="truncate">{geoLine || "Locality unrecorded"}</span>
                    <span className="shrink-0 font-mono text-[11px]">
                      {spec.event_date || spec.verbatim_event_date || "s.d."}
                    </span>
                  </div>
                </div>
              </Link>
            );
          })}
        </div>
      ) : (
        <div className="overflow-x-auto rounded-lg border border-border bg-surface">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-surface-muted/70 text-xs uppercase tracking-wider text-ink-muted">
              <tr>
                <th className="px-4 py-3 font-medium">Catalog ID</th>
                <th className="px-4 py-3 font-medium">Taxon</th>
                <th className="px-4 py-3 font-medium">Family</th>
                <th className="px-4 py-3 font-medium">Collector</th>
                <th className="px-4 py-3 font-medium">Date</th>
                <th className="px-4 py-3 font-medium">Region</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {specimens.map((spec) => {
                const taxon = spec.current_determination?.taxon;
                return (
                  <tr key={spec.id} className="hover:bg-surface-muted/40 transition-colors">
                    <td className="px-4 py-3 font-mono text-xs whitespace-nowrap">
                      <Link
                        href={`/h/${herbarium.slug}/specimens/${encodeURIComponent(spec.catalog_number)}`}
                        className="font-medium text-primary hover:underline"
                      >
                        {spec.catalog_number}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      <ScientificName
                        name={taxon?.scientific_name}
                        authorship={taxon?.scientific_name_authorship}
                        qualifier={spec.current_determination?.identification_qualifier}
                      />
                    </td>
                    <td className="px-4 py-3 text-xs text-ink-muted">{taxon?.family || "—"}</td>
                    <td className="px-4 py-3 text-xs text-ink">{spec.recorded_by || "—"}</td>
                    <td className="px-4 py-3 font-mono text-xs text-ink-muted whitespace-nowrap">
                      {spec.event_date || spec.verbatim_event_date || "—"}
                    </td>
                    <td className="px-4 py-3 text-xs text-ink-muted">
                      {[spec.location.state_province, spec.location.country].filter(Boolean).join(", ") || "—"}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {/* Pagination */}
      {view !== "tree" && view !== "map" && meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between pt-2 text-sm">
          <span className="text-xs text-ink-muted">
            Page {meta.current_page} of {meta.last_page} ({meta.total} total)
          </span>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= meta.last_page}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
