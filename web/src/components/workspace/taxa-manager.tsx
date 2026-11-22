"use client";

import { useEffect, useState, useCallback } from "react";
import { Plus, Search, ShieldAlert, Sparkles, Trash2 } from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type { Herbarium, PaginatedResponse, Taxon, TaxonomyLookupResult } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input, Select, Textarea } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { ScientificName } from "@/components/botanical/scientific-name";

export function TaxaManager({ herbarium }: { herbarium: Herbarium }) {
  const canCurate =
    herbarium.current_user_role === "owner" || herbarium.current_user_role === "curator";

  const [taxa, setTaxa] = useState<Taxon[]>([]);
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // External Lookup
  const [lookupSource, setLookupSource] = useState<"gbif" | "powo">("gbif");
  const [lookupQuery, setLookupQuery] = useState("");
  const [lookupResults, setLookupResults] = useState<TaxonomyLookupResult[]>([]);
  const [lookingUp, setLookingUp] = useState(false);

  // Create / Edit form
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [scientificName, setScientificName] = useState("");
  const [authorship, setAuthorship] = useState("");
  const [family, setFamily] = useState("");
  const [genus, setGenus] = useState("");
  const [commonName, setCommonName] = useState("");
  const [description, setDescription] = useState("");
  const [ecology, setEcology] = useState("");
  const [isSensitive, setIsSensitive] = useState(false);
  const [externalSource, setExternalSource] = useState<"manual" | "gbif" | "powo">("manual");
  const [externalId, setExternalId] = useState<string | null>(null);

  const loadTaxa = useCallback(async () => {
    setLoading(true);
    try {
      const qs = search.trim() ? `?q=${encodeURIComponent(search.trim())}&per_page=100` : "?per_page=100";
      const res = await apiFetch<PaginatedResponse<Taxon>>(
        `/api/v1/herbaria/${herbarium.slug}/taxa${qs}`,
      );
      setTaxa(res.data);
    } catch {
      setTaxa([]);
    } finally {
      setLoading(false);
    }
  }, [herbarium.slug, search]);

  useEffect(() => {
    const t = setTimeout(loadTaxa, 200);
    return () => clearTimeout(t);
  }, [loadTaxa]);

  async function runExternalLookup(e: React.FormEvent) {
    e.preventDefault();
    if (lookupQuery.trim().length < 2) return;
    setLookingUp(true);
    setError(null);
    try {
      const res = await apiFetch<{ data: TaxonomyLookupResult[] }>(
        `/api/v1/taxonomy/lookup?q=${encodeURIComponent(lookupQuery.trim())}&source=${lookupSource}`,
      );
      setLookupResults(res.data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Taxonomy lookup failed.");
    } finally {
      setLookingUp(false);
    }
  }

  function prefillFromLookup(item: TaxonomyLookupResult) {
    setEditingId(null);
    setScientificName(item.scientific_name);
    setAuthorship(item.scientific_name_authorship || "");
    setFamily(item.family || "");
    setGenus(item.genus || "");
    setCommonName(item.common_names?.[0]?.name || "");
    setDescription("");
    setEcology("");
    setIsSensitive(false);
    setExternalSource(item.external_source);
    setExternalId(item.external_taxon_id);
    setShowForm(true);
    setLookupResults([]);
  }

  function startEdit(t: Taxon) {
    setEditingId(t.id);
    setScientificName(t.scientific_name);
    setAuthorship(t.scientific_name_authorship || "");
    setFamily(t.family || "");
    setGenus(t.genus || "");
    setCommonName(t.common_names?.map((c) => c.name).join(", ") || "");
    setDescription(t.description_markdown || "");
    setEcology(t.ecology_notes || "");
    setIsSensitive(t.is_sensitive_location);
    setExternalSource(t.external_source);
    setExternalId(t.external_taxon_id);
    setShowForm(true);
  }

  async function handleSave(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const commonNames = commonName
      .split(",")
      .map((s) => s.trim())
      .filter(Boolean)
      .map((name) => ({ name, language: "en" }));

    const payload = {
      scientific_name: scientificName.trim(),
      scientific_name_authorship: authorship.trim() || null,
      family: family.trim() || null,
      genus: genus.trim() || null,
      common_names: commonNames,
      description_markdown: description.trim() || null,
      ecology_notes: ecology.trim() || null,
      is_sensitive_location: isSensitive,
      external_source: externalSource,
      external_taxon_id: externalId,
    };

    try {
      if (editingId) {
        await apiFetch(`/api/v1/herbaria/${herbarium.slug}/taxa/${editingId}`, {
          method: "PATCH",
          json: payload,
        });
      } else {
        await apiFetch(`/api/v1/herbaria/${herbarium.slug}/taxa`, {
          method: "POST",
          json: payload,
        });
      }
      setShowForm(false);
      setEditingId(null);
      await loadTaxa();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save taxon.");
    }
  }

  async function handleDelete(id: string) {
    if (!confirm("Archive this taxon? Existing determinations will remain readable.")) return;
    await apiFetch(`/api/v1/herbaria/${herbarium.slug}/taxa/${id}`, { method: "DELETE" });
    await loadTaxa();
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-serif text-3xl font-semibold text-ink">Plant Taxonomy</h1>
          <p className="text-sm text-ink-muted">
            Manage herbarium-scoped species descriptions, sensitivity flags, and GBIF/POWO lookups.
          </p>
        </div>
        {canCurate && (
          <Button
            onClick={() => {
              setEditingId(null);
              setScientificName("");
              setAuthorship("");
              setFamily("");
              setGenus("");
              setCommonName("");
              setDescription("");
              setEcology("");
              setIsSensitive(false);
              setExternalSource("manual");
              setExternalId(null);
              setShowForm((s) => !s);
            }}
          >
            <Plus className="h-4 w-4" />
            Add Taxon Manually
          </Button>
        )}
      </div>

      {error && (
        <div className="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* External GBIF / Kew POWO Lookup Box */}
      {canCurate && (
        <section className="rounded-lg border border-border bg-surface p-5 space-y-3">
          <h2 className="flex items-center gap-2 font-serif text-lg font-semibold">
            <Sparkles className="h-4 w-4 text-accent" />
            Botanical Backbone Autocomplete (GBIF / Kew POWO)
          </h2>
          <form onSubmit={runExternalLookup} className="flex flex-col gap-2 sm:flex-row">
            <Select
              value={lookupSource}
              onChange={(e) => setLookupSource(e.target.value as "gbif" | "powo")}
              className="sm:w-44"
            >
              <option value="gbif">GBIF Backbone</option>
              <option value="powo">Kew POWO / IPNI</option>
            </Select>
            <Input
              value={lookupQuery}
              onChange={(e) => setLookupQuery(e.target.value)}
              placeholder="Search Latin name (e.g. Quercus garryana, Trillium ovatum)…"
              className="flex-1"
            />
            <Button type="submit" variant="secondary" disabled={lookingUp}>
              <Search className="h-3.5 w-3.5" />
              {lookingUp ? "Searching…" : "Search Backbone"}
            </Button>
          </form>

          {lookupResults.length > 0 && (
            <ul className="divide-y divide-border rounded-md border border-border bg-surface-muted/40">
              {lookupResults.map((item) => (
                <li
                  key={`${item.external_taxon_id}-${item.scientific_name}`}
                  className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm"
                >
                  <div>
                    <ScientificName
                      name={item.scientific_name}
                      authorship={item.scientific_name_authorship}
                    />
                    <span className="ml-2 text-xs text-ink-muted">
                      {[item.family, item.taxon_order].filter(Boolean).join(" · ")}
                    </span>
                  </div>
                  <Button size="sm" variant="outline" onClick={() => prefillFromLookup(item)}>
                    Import into Herbarium
                  </Button>
                </li>
              ))}
            </ul>
          )}
        </section>
      )}

      {/* Manual / Prefilled Taxon Form */}
      {showForm && canCurate && (
        <form
          onSubmit={handleSave}
          className="rounded-lg border border-primary/30 bg-surface p-5 space-y-4 shadow-xs"
        >
          <h2 className="font-serif text-lg font-semibold">
            {editingId ? "Edit Taxon" : "New Herbarium Taxon"}
          </h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1 sm:col-span-2">
              <label className="block text-xs font-medium">Scientific Name *</label>
              <Input
                required
                value={scientificName}
                onChange={(e) => setScientificName(e.target.value)}
                placeholder="Quercus garryana"
              />
            </div>
            <div className="space-y-1 sm:col-span-2">
              <label className="block text-xs font-medium">Authorship</label>
              <Input
                value={authorship}
                onChange={(e) => setAuthorship(e.target.value)}
                placeholder="Douglas ex Hook."
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Family</label>
              <Input value={family} onChange={(e) => setFamily(e.target.value)} placeholder="Fagaceae" />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Genus</label>
              <Input value={genus} onChange={(e) => setGenus(e.target.value)} placeholder="Quercus" />
            </div>
            <div className="space-y-1 sm:col-span-2">
              <label className="block text-xs font-medium">Common Names (comma-separated)</label>
              <Input
                value={commonName}
                onChange={(e) => setCommonName(e.target.value)}
                placeholder="Oregon White Oak, Garry Oak"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Botanical Description</label>
              <Textarea value={description} onChange={(e) => setDescription(e.target.value)} rows={3} />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Ecology &amp; Habitat Notes</label>
              <Textarea value={ecology} onChange={(e) => setEcology(e.target.value)} rows={3} />
            </div>
          </div>

          <label className="flex items-center gap-2.5 text-sm cursor-pointer">
            <input
              type="checkbox"
              checked={isSensitive}
              onChange={(e) => setIsSensitive(e.target.checked)}
              className="h-4 w-4 rounded border-border accent-primary"
            />
            <span>
              <strong>Sensitive / Threatened Species:</strong> Automatically obscure public specimen
              coordinates (~10 km grid)
            </span>
          </label>

          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" onClick={() => setShowForm(false)}>
              Cancel
            </Button>
            <Button type="submit">{editingId ? "Update Taxon" : "Save Taxon"}</Button>
          </div>
        </form>
      )}

      {/* Taxa Catalog Table */}
      <div className="space-y-3">
        <div className="relative max-w-md">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Filter herbarium taxa…"
            className="pl-9"
          />
        </div>

        <div className="overflow-x-auto rounded-lg border border-border bg-surface">
          <table className="w-full text-left text-sm">
            <thead className="border-b border-border bg-surface-muted/70 text-xs uppercase tracking-wider text-ink-muted">
              <tr>
                <th className="px-4 py-3 font-medium">Scientific Name</th>
                <th className="px-4 py-3 font-medium">Family</th>
                <th className="px-4 py-3 font-medium">Common Names</th>
                <th className="px-4 py-3 font-medium">Specimens</th>
                <th className="px-4 py-3 font-medium">Privacy</th>
                {canCurate && <th className="px-4 py-3 font-medium text-right">Actions</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-ink-muted">
                    Loading taxa…
                  </td>
                </tr>
              ) : taxa.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-6 text-center text-ink-muted">
                    No taxa cataloged yet. Use the GBIF/POWO lookup above or add one manually.
                  </td>
                </tr>
              ) : (
                taxa.map((t) => (
                  <tr key={t.id} className="hover:bg-surface-muted/30">
                    <td className="px-4 py-3">
                      <ScientificName
                        name={t.scientific_name}
                        authorship={t.scientific_name_authorship}
                      />
                    </td>
                    <td className="px-4 py-3 text-xs text-ink-muted">{t.family || "—"}</td>
                    <td className="px-4 py-3 text-xs text-ink-muted">
                      {t.common_names?.map((c) => c.name).join(", ") || "—"}
                    </td>
                    <td className="px-4 py-3 font-mono text-xs">{t.specimens_count ?? 0}</td>
                    <td className="px-4 py-3">
                      {t.is_sensitive_location ? (
                        <Badge tone="accent">
                          <ShieldAlert className="h-3 w-3" /> Sensitive
                        </Badge>
                      ) : (
                        <Badge tone="neutral">Standard</Badge>
                      )}
                    </td>
                    {canCurate && (
                      <td className="px-4 py-3 text-right whitespace-nowrap space-x-2">
                        <Button size="sm" variant="ghost" onClick={() => startEdit(t)}>
                          Edit
                        </Button>
                        <Button size="icon" variant="ghost" onClick={() => handleDelete(t.id)} title="Archive taxon">
                          <Trash2 className="h-3.5 w-3.5 text-danger" />
                        </Button>
                      </td>
                    )}
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
