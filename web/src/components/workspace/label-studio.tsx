"use client";

import { useEffect, useState } from "react";
import { Printer, QrCode } from "lucide-react";
import { apiDownloadBlob, apiFetch, ApiError } from "@/lib/api/client";
import type { Herbarium, PaginatedResponse, Specimen } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/input";
import { ScientificName } from "@/components/botanical/scientific-name";

export function LabelStudio({ herbarium }: { herbarium: Herbarium }) {
  const [specimens, setSpecimens] = useState<Specimen[]>([]);
  const [selected, setSelected] = useState<Record<string, boolean>>({});
  const [template, setTemplate] = useState("letter_4up");
  const [includeExact, setIncludeExact] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    apiFetch<PaginatedResponse<Specimen>>(
      `/api/v1/herbaria/${herbarium.slug}/specimens?per_page=100`,
    )
      .then((res) => {
        setSpecimens(res.data);
        const initial: Record<string, boolean> = {};
        res.data.slice(0, 4).forEach((s) => {
          initial[s.catalog_number] = true;
        });
        setSelected(initial);
      })
      .catch(() => {});
  }, [herbarium.slug]);

  const selectedList = Object.entries(selected)
    .filter(([, checked]) => checked)
    .map(([cat]) => cat);

  function toggleAll(checked: boolean) {
    const next: Record<string, boolean> = {};
    if (checked) {
      specimens.forEach((s) => {
        next[s.catalog_number] = true;
      });
    }
    setSelected(next);
  }

  async function handleGenerate() {
    if (selectedList.length === 0) return;
    setGenerating(true);
    setError(null);
    try {
      await apiDownloadBlob(
        `/api/v1/herbaria/${herbarium.slug}/labels/pdf`,
        {
          method: "POST",
          json: {
            catalog_numbers: selectedList,
            template,
            include_exact_coordinates: includeExact,
          },
        },
        `${herbarium.slug}-labels.pdf`,
      );
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to generate PDF labels.");
    } finally {
      setGenerating(false);
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-serif text-3xl font-semibold text-ink">Printable Herbarium Labels</h1>
        <p className="text-sm text-ink-muted mt-1">
          Generate archival print-ready PDF labels with scannable QR codes linking directly back to
          each specimen&apos;s digital permalink.
        </p>
      </div>

      {error && (
        <div className="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* Controls */}
      <div className="rounded-lg border border-border bg-surface p-5 flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-wrap items-end gap-4">
          <div className="space-y-1">
            <label className="block text-xs font-medium">Paper / Label Template</label>
            <Select value={template} onChange={(e) => setTemplate(e.target.value)} className="w-56">
              <option value="letter_4up">US Letter — 4 Labels per Page</option>
              <option value="a4_4up">A4 — 4 Labels per Page</option>
              <option value="single_4x3">Single 4&quot; × 3&quot; Label Printer</option>
            </Select>
          </div>

          {herbarium.can_view_exact_coordinates && (
            <label className="flex items-center gap-2 text-xs pb-2 cursor-pointer">
              <input
                type="checkbox"
                checked={includeExact}
                onChange={(e) => setIncludeExact(e.target.checked)}
                className="h-4 w-4 accent-primary"
              />
              <span>Print exact GPS for obscured/sensitive specimens</span>
            </label>
          )}
        </div>

        <Button onClick={handleGenerate} disabled={generating || selectedList.length === 0}>
          <Printer className="h-4 w-4" />
          {generating ? "Rendering PDF…" : `Download PDF (${selectedList.length} selected)`}
        </Button>
      </div>

      {/* Specimen Selection List */}
      <div className="rounded-lg border border-border bg-surface overflow-hidden">
        <div className="flex items-center justify-between border-b border-border bg-surface-muted/60 px-4 py-2.5 text-xs">
          <span className="font-medium text-ink flex items-center gap-2">
            <QrCode className="h-3.5 w-3.5 text-primary" />
            Select Specimens to Print
          </span>
          <div className="space-x-3">
            <button
              type="button"
              onClick={() => toggleAll(true)}
              className="text-primary hover:underline cursor-pointer"
            >
              Select all
            </button>
            <button
              type="button"
              onClick={() => toggleAll(false)}
              className="text-ink-muted hover:underline cursor-pointer"
            >
              Clear
            </button>
          </div>
        </div>

        <div className="divide-y divide-border max-h-[480px] overflow-y-auto">
          {specimens.map((s) => (
            <label
              key={s.id}
              className="flex items-center gap-3 px-4 py-3 text-sm hover:bg-surface-muted/30 cursor-pointer"
            >
              <input
                type="checkbox"
                checked={!!selected[s.catalog_number]}
                onChange={(e) =>
                  setSelected((prev) => ({ ...prev, [s.catalog_number]: e.target.checked }))
                }
                className="h-4 w-4 accent-primary"
              />
              <span className="font-mono text-xs font-medium text-primary w-32 shrink-0">
                {s.catalog_number}
              </span>
              <span className="flex-1 truncate">
                <ScientificName name={s.current_determination?.taxon?.scientific_name} />
              </span>
              <span className="text-xs text-ink-muted hidden sm:inline">
                {s.recorded_by || "—"}
              </span>
            </label>
          ))}
        </div>
      </div>
    </div>
  );
}
