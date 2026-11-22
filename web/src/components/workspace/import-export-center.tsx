"use client";

import { useEffect, useState, useCallback } from "react";
import { CheckCircle2, Download, FileArchive, FileSpreadsheet, Upload } from "lucide-react";
import { apiDownloadBlob, apiFetch, ApiError } from "@/lib/api/client";
import type { Herbarium, ImportExportJobItem } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Select } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";

interface CsvPreview {
  headers: string[];
  total_rows: number;
  sample_rows: Array<Record<string, string>>;
  suggested_mapping: Record<string, string>;
  supported_fields: string[];
}

interface ImportSummary {
  dry_run: boolean;
  total_rows: number;
  created: number;
  taxa_created: number;
  skipped: number;
  errors: Array<{ row: number; message: string }>;
}

const KEY_FIELDS = [
  "catalog_number",
  "field_number",
  "scientific_name",
  "scientific_name_authorship",
  "family",
  "genus",
  "recorded_by",
  "event_date",
  "country",
  "state_province",
  "county",
  "locality",
  "habitat",
  "elevation_min_m",
  "decimal_latitude",
  "decimal_longitude",
  "geoprivacy",
  "storage_cabinet",
  "storage_folder",
];

export function ImportExportCenter({ herbarium }: { herbarium: Herbarium }) {
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<CsvPreview | null>(null);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [defaultVisibility, setDefaultVisibility] = useState("draft");
  const [summary, setSummary] = useState<ImportSummary | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [jobs, setJobs] = useState<ImportExportJobItem[]>([]);

  const loadJobs = useCallback(async () => {
    try {
      const res = await apiFetch<{ data: ImportExportJobItem[] }>(
        `/api/v1/herbaria/${herbarium.slug}/jobs`,
      );
      setJobs(res.data);
    } catch {
      setJobs([]);
    }
  }, [herbarium.slug]);

  useEffect(() => {
    let active = true;
    apiFetch<{ data: ImportExportJobItem[] }>(`/api/v1/herbaria/${herbarium.slug}/jobs`)
      .then((res) => {
        if (active) setJobs(res.data);
      })
      .catch(() => {
        if (active) setJobs([]);
      });
    return () => {
      active = false;
    };
  }, [herbarium.slug]);

  async function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
    const selected = e.target.files?.[0];
    if (!selected) return;
    setFile(selected);
    setSummary(null);
    setError(null);
    setBusy(true);

    const fd = new FormData();
    fd.append("file", selected);

    try {
      const res = await apiFetch<CsvPreview>(
        `/api/v1/herbaria/${herbarium.slug}/imports/csv/preview`,
        { method: "POST", body: fd },
      );
      setPreview(res);
      setMapping(res.suggested_mapping || {});
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not parse CSV file.");
    } finally {
      setBusy(false);
    }
  }

  async function runImport(dryRun: boolean) {
    if (!file) return;
    setBusy(true);
    setError(null);
    const fd = new FormData();
    fd.append("file", file);
    fd.append("mapping", JSON.stringify(mapping));
    fd.append("dry_run", dryRun ? "1" : "0");
    fd.append("default_visibility", defaultVisibility);

    try {
      const res = await apiFetch<{ summary: ImportSummary }>(
        `/api/v1/herbaria/${herbarium.slug}/imports/csv`,
        { method: "POST", body: fd },
      );
      setSummary(res.summary);
      if (!dryRun) {
        await loadJobs();
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "CSV import failed.");
    } finally {
      setBusy(false);
    }
  }

  async function triggerExport(type: "csv" | "dwca") {
    setError(null);
    try {
      await apiDownloadBlob(
        `/api/v1/herbaria/${herbarium.slug}/exports/${type}`,
        { method: "POST" },
        `${herbarium.slug}-${type}.${type === "dwca" ? "zip" : "csv"}`,
      );
      await loadJobs();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Export failed.");
    }
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="font-serif text-3xl font-semibold text-ink">Import &amp; Data Portability</h1>
        <p className="text-sm text-ink-muted mt-1">
          Migrate existing spreadsheets via mapped CSV import or publish GBIF-ready Darwin Core Archives.
        </p>
      </div>

      {error && (
        <div className="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* Export Cards */}
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div className="rounded-lg border border-border bg-surface p-5 flex flex-col justify-between gap-4">
          <div className="space-y-1.5">
            <div className="flex items-center gap-2 text-primary">
              <FileArchive className="h-5 w-5" />
              <h2 className="font-serif text-lg font-semibold text-ink">
                Darwin Core Archive (DwC-A)
              </h2>
            </div>
            <p className="text-xs leading-relaxed text-ink-muted">
              Generates a standards-compliant ZIP archive (<code className="font-mono">meta.xml</code>,{" "}
              <code className="font-mono">eml.xml</code>, <code className="font-mono">occurrence.txt</code>,{" "}
              <code className="font-mono">multimedia.txt</code>) ready for GBIF, iDigBio, or archival backup.
              Sensitive coordinates are automatically obscured for public compliance.
            </p>
          </div>
          <Button onClick={() => triggerExport("dwca")}>
            <Download className="h-4 w-4" /> Export DwC-A (.zip)
          </Button>
        </div>

        <div className="rounded-lg border border-border bg-surface p-5 flex flex-col justify-between gap-4">
          <div className="space-y-1.5">
            <div className="flex items-center gap-2 text-accent">
              <FileSpreadsheet className="h-5 w-5" />
              <h2 className="font-serif text-lg font-semibold text-ink">Darwin Core CSV Export</h2>
            </div>
            <p className="text-xs leading-relaxed text-ink-muted">
              Download all accessible specimens in your herbarium as a clean UTF-8 spreadsheet with
              Darwin Core column headers for analysis in R, Excel, or QGIS.
            </p>
          </div>
          <Button variant="outline" onClick={() => triggerExport("csv")}>
            <Download className="h-4 w-4" /> Export CSV (.csv)
          </Button>
        </div>
      </div>

      {/* CSV Column-Mapping Import Wizard */}
      <section className="rounded-lg border border-border bg-surface p-6 space-y-5">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="font-serif text-xl font-semibold text-ink">CSV Import Wizard</h2>
            <p className="text-xs text-ink-muted">
              Upload any spreadsheet CSV. Columns are automatically matched to Darwin Core &amp; Herbarium fields.
            </p>
          </div>
          <label className="inline-flex h-9 cursor-pointer items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary-hover">
            <Upload className="h-4 w-4" />
            <span>{file ? file.name : "Select CSV File"}</span>
            <input type="file" accept=".csv,text/csv" onChange={handleFileSelect} className="hidden" />
          </label>
        </div>

        {preview && (
          <div className="space-y-5 border-t border-border pt-5">
            <div className="flex flex-wrap items-center justify-between gap-3 text-xs">
              <span className="text-ink-muted">
                Detected <strong className="text-ink">{preview.total_rows}</strong> data rows across{" "}
                <strong className="text-ink">{preview.headers.length}</strong> columns.
              </span>
              <div className="flex items-center gap-2">
                <span className="text-ink-muted">Default visibility:</span>
                <Select
                  value={defaultVisibility}
                  onChange={(e) => setDefaultVisibility(e.target.value)}
                  className="w-36 h-8 text-xs"
                >
                  <option value="draft">Draft</option>
                  <option value="private">Private</option>
                  <option value="published">Published</option>
                </Select>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {KEY_FIELDS.map((field) => (
                <div key={field} className="space-y-1">
                  <label className="block font-mono text-[11px] text-ink-muted">{field}</label>
                  <Select
                    value={mapping[field] || ""}
                    onChange={(e) =>
                      setMapping((prev) => ({ ...prev, [field]: e.target.value }))
                    }
                    className="h-8 text-xs"
                  >
                    <option value="">— Skip —</option>
                    {preview.headers.map((h) => (
                      <option key={h} value={h}>
                        {h}
                      </option>
                    ))}
                  </Select>
                </div>
              ))}
            </div>

            <div className="flex flex-wrap items-center gap-2 pt-2">
              <Button variant="outline" onClick={() => runImport(true)} disabled={busy}>
                Validate (Dry Run)
              </Button>
              <Button onClick={() => runImport(false)} disabled={busy}>
                {busy ? "Importing…" : `Import ${preview.total_rows} Rows`}
              </Button>
            </div>
          </div>
        )}

        {summary && (
          <div className="rounded-md border border-success/30 bg-success-soft p-4 space-y-2 text-xs">
            <div className="flex items-center gap-2 font-medium text-success">
              <CheckCircle2 className="h-4 w-4" />
              <span>
                {summary.dry_run ? "Dry-Run Validation Complete" : "CSV Import Completed"}
              </span>
            </div>
            <p className="text-ink">
              Processed <strong>{summary.total_rows}</strong> rows ·{" "}
              <strong>{summary.created}</strong> {summary.dry_run ? "valid" : "specimens created"} ·{" "}
              <strong>{summary.taxa_created}</strong> new taxa created ·{" "}
              <strong>{summary.skipped}</strong> skipped.
            </p>
            {summary.errors.length > 0 && (
              <ul className="mt-2 list-disc pl-5 text-danger space-y-0.5">
                {summary.errors.slice(0, 10).map((e, i) => (
                  <li key={i}>
                    Row {e.row}: {e.message}
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </section>

      {/* Job History */}
      {jobs.length > 0 && (
        <section className="rounded-lg border border-border bg-surface p-5 space-y-3">
          <h2 className="font-serif text-lg font-semibold">Recent Import &amp; Export History</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="border-b border-border text-ink-muted uppercase">
                <tr>
                  <th className="py-2 pr-4">Type</th>
                  <th className="py-2 pr-4">Status</th>
                  <th className="py-2 pr-4">Date</th>
                  <th className="py-2 text-right">Archive</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {jobs.map((j) => (
                  <tr key={j.id}>
                    <td className="py-2.5 pr-4 font-mono">{j.type}</td>
                    <td className="py-2.5 pr-4">
                      <Badge tone={j.status === "completed" ? "success" : "neutral"}>
                        {j.status}
                      </Badge>
                    </td>
                    <td className="py-2.5 pr-4 text-ink-muted">
                      {j.created_at ? new Date(j.created_at).toLocaleString() : "—"}
                    </td>
                    <td className="py-2.5 text-right">
                      {j.has_download ? (
                        <a
                          href={`/api/v1/herbaria/${herbarium.slug}/jobs/${j.id}/download`}
                          className="text-primary hover:underline"
                        >
                          Download
                        </a>
                      ) : (
                        <span className="text-ink-muted">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </div>
  );
}
