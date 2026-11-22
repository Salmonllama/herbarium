"use client";

import { useState } from "react";
import Link from "next/link";
import {
  Check,
  Copy,
  MapPin,
  ShieldAlert,
  History,
  FileText,
  Archive,
  Pencil,
} from "lucide-react";
import type { GeoJsonFeature, Herbarium, Specimen } from "@/lib/api/types";
import { formatBibtex, formatCitation, formatElevation } from "@/lib/utils";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { ScientificName } from "./scientific-name";
import { ZoomViewer } from "./zoom-viewer";
import { SpecimenMap } from "./specimen-map";

export function SpecimenDetailClient({
  herbarium,
  specimen,
}: {
  herbarium: Herbarium;
  specimen: Specimen;
}) {
  const [copiedMode, setCopiedMode] = useState<"cite" | "bibtex" | "url" | null>(null);
  const taxon = specimen.current_determination?.taxon;
  const elevation = formatElevation(specimen.location.elevation_min_m, specimen.location.elevation_max_m);

  const canEdit =
    herbarium.current_user_role === "owner" || herbarium.current_user_role === "curator";

  function copyText(text: string, mode: "cite" | "bibtex" | "url") {
    navigator.clipboard.writeText(text).then(() => {
      setCopiedMode(mode);
      setTimeout(() => setCopiedMode(null), 2000);
    });
  }

  const mapFeatures: GeoJsonFeature[] =
    specimen.location.decimal_latitude != null && specimen.location.decimal_longitude != null
      ? [
          {
            type: "Feature",
            geometry: {
              type: "Point",
              coordinates: [specimen.location.decimal_longitude, specimen.location.decimal_latitude],
            },
            properties: {
              id: specimen.id,
              catalog_number: specimen.catalog_number,
              scientific_name: taxon?.scientific_name || "Indeterminata",
              family: taxon?.family || null,
              recorded_by: specimen.recorded_by,
              event_date: specimen.event_date,
              country: specimen.location.country,
              state_province: specimen.location.state_province,
              coordinates_obscured: specimen.location.coordinates_obscured,
              coordinate_uncertainty_m: specimen.location.coordinate_uncertainty_m,
              thumb_url: specimen.media?.[0]?.thumb_url || null,
            },
          },
        ]
      : [];

  return (
    <div className="space-y-8">
      {/* Breadcrumb & Actions */}
      <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
        <nav className="flex items-center gap-2 text-ink-muted">
          <Link href={`/h/${herbarium.slug}`} className="hover:text-ink transition-colors">
            {herbarium.name}
          </Link>
          <span>/</span>
          <span className="font-mono text-ink font-medium">{specimen.catalog_number}</span>
        </nav>

        <div className="flex items-center gap-2">
          {canEdit && (
            <Link
              href={`/app/${herbarium.slug}/specimens/${encodeURIComponent(specimen.catalog_number)}/edit`}
            >
              <Button variant="outline" size="sm">
                <Pencil className="h-3.5 w-3.5" />
                Edit Specimen
              </Button>
            </Link>
          )}
        </div>
      </div>

      {/* Main 2-column archival layout */}
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
        {/* Left: High-res Pan/Zoom Viewer */}
        <div className="lg:col-span-6">
          <ZoomViewer
            media={specimen.media || []}
            alt={`${taxon?.scientific_name || "Specimen"} (${specimen.catalog_number})`}
          />
        </div>

        {/* Right: Archival Herbarium Label Header + Metadata */}
        <div className="lg:col-span-6 space-y-6">
          {/* Herbarium Sheet Label Card */}
          <section className="rounded-lg border border-border bg-surface p-6 shadow-2xs space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border pb-3">
              <div className="flex items-center gap-2">
                <span className="font-mono text-sm font-semibold tracking-tight text-primary">
                  {specimen.catalog_number}
                </span>
                {specimen.field_number && (
                  <span className="font-mono text-xs text-ink-muted">
                    · Field #{specimen.field_number}
                  </span>
                )}
              </div>
              <div className="flex items-center gap-1.5">
                {specimen.type_status && <Badge tone="danger">{specimen.type_status}</Badge>}
                <Badge tone={specimen.visibility === "published" ? "success" : "accent"}>
                  {specimen.visibility}
                </Badge>
                <Badge tone="neutral">{specimen.curation_status}</Badge>
              </div>
            </div>

            <div>
              <div className="text-xs font-semibold uppercase tracking-widest text-accent">
                {taxon?.family || "Family Unassigned"}
                {taxon?.taxon_order ? ` · ${taxon.taxon_order}` : ""}
              </div>
              <h1 className="mt-1 text-3xl leading-tight">
                <ScientificName
                  name={taxon?.scientific_name}
                  authorship={taxon?.scientific_name_authorship}
                  qualifier={specimen.current_determination?.identification_qualifier}
                />
              </h1>
              {taxon?.common_names && taxon.common_names.length > 0 && (
                <p className="mt-1 text-sm text-ink-muted">
                  {taxon.common_names.map((c) => c.name).join(" · ")}
                </p>
              )}
            </div>

            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 pt-2 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase tracking-wider text-ink-muted">Collector (Leg.)</dt>
                <dd className="mt-0.5 font-medium text-ink">{specimen.recorded_by || "—"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wider text-ink-muted">Collection Date</dt>
                <dd className="mt-0.5 font-mono text-xs text-ink">
                  {specimen.event_date || specimen.verbatim_event_date || "s.d."}
                </dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wider text-ink-muted">Preparations</dt>
                <dd className="mt-0.5 text-ink">{specimen.preparations || "Pressed sheet"}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wider text-ink-muted">Phenology</dt>
                <dd className="mt-0.5 text-ink capitalize">
                  {specimen.reproductive_condition || "—"}
                </dd>
              </div>
            </dl>

            {(specimen.habitat || specimen.substrate || specimen.occurrence_remarks) && (
              <div className="border-t border-border pt-4 space-y-2 text-sm">
                {specimen.habitat && (
                  <p>
                    <span className="font-medium text-ink">Habitat: </span>
                    <span className="text-ink-muted">{specimen.habitat}</span>
                  </p>
                )}
                {specimen.substrate && (
                  <p>
                    <span className="font-medium text-ink">Substrate: </span>
                    <span className="text-ink-muted">{specimen.substrate}</span>
                  </p>
                )}
                {specimen.occurrence_remarks && (
                  <p>
                    <span className="font-medium text-ink">Remarks: </span>
                    <span className="text-ink-muted">{specimen.occurrence_remarks}</span>
                  </p>
                )}
              </div>
            )}

            {/* Citation Bar */}
            <div className="border-t border-border pt-4 flex flex-wrap items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => copyText(formatCitation(herbarium, specimen), "cite")}
              >
                {copiedMode === "cite" ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                {copiedMode === "cite" ? "Citation copied" : "Copy Citation"}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => copyText(formatBibtex(herbarium, specimen), "bibtex")}
              >
                {copiedMode === "bibtex" ? <Check className="h-3.5 w-3.5" /> : <FileText className="h-3.5 w-3.5" />}
                {copiedMode === "bibtex" ? "BibTeX copied" : "Copy BibTeX"}
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() =>
                  copyText(
                    typeof window !== "undefined" ? window.location.href : specimen.permalink || "",
                    "url",
                  )
                }
              >
                {copiedMode === "url" ? "Link copied!" : "Copy Permalink"}
              </Button>
            </div>
          </section>

          {/* Taxon Botanical Description */}
          {taxon && (taxon.description_markdown || taxon.ecology_notes || taxon.morphology_notes) && (
            <section className="rounded-lg border border-border bg-surface p-5 space-y-2.5">
              <h2 className="font-serif text-lg font-semibold text-ink">Species Notes</h2>
              {taxon.description_markdown && (
                <p className="text-sm leading-relaxed text-ink-muted whitespace-pre-line">
                  {taxon.description_markdown}
                </p>
              )}
              {taxon.ecology_notes && (
                <p className="text-xs text-ink-muted">
                  <strong className="text-ink">Ecology:</strong> {taxon.ecology_notes}
                </p>
              )}
              {taxon.morphology_notes && (
                <p className="text-xs text-ink-muted">
                  <strong className="text-ink">Morphology:</strong> {taxon.morphology_notes}
                </p>
              )}
            </section>
          )}

          {/* Physical Storage (Visible to Herbarium Members) */}
          {specimen.storage &&
            (specimen.storage.room ||
              specimen.storage.cabinet ||
              specimen.storage.shelf ||
              specimen.storage.folder) && (
              <section className="rounded-lg border border-border bg-surface p-5">
                <h2 className="flex items-center gap-2 font-serif text-lg font-semibold text-ink mb-3">
                  <Archive className="h-4 w-4 text-accent" />
                  Physical Archival Location
                </h2>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 font-mono text-xs">
                  <div>
                    <span className="block text-[10px] uppercase text-ink-muted">Room</span>
                    <span>{specimen.storage.room || "—"}</span>
                  </div>
                  <div>
                    <span className="block text-[10px] uppercase text-ink-muted">Cabinet</span>
                    <span>{specimen.storage.cabinet || "—"}</span>
                  </div>
                  <div>
                    <span className="block text-[10px] uppercase text-ink-muted">Shelf</span>
                    <span>{specimen.storage.shelf || "—"}</span>
                  </div>
                  <div>
                    <span className="block text-[10px] uppercase text-ink-muted">Folder</span>
                    <span>{specimen.storage.folder || "—"}</span>
                  </div>
                </div>
              </section>
            )}
        </div>
      </div>

      {/* Location + Determination History Grid */}
      <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
        {/* Locality & Map */}
        <section className="lg:col-span-7 rounded-lg border border-border bg-surface p-6 space-y-4">
          <div className="flex items-center justify-between gap-2">
            <h2 className="flex items-center gap-2 font-serif text-xl font-semibold">
              <MapPin className="h-4 w-4 text-primary" />
              Locality &amp; Georeference
            </h2>
            {specimen.location.coordinates_obscured && (
              <Badge tone="accent">
                <ShieldAlert className="h-3 w-3" />
                Location Protected ({specimen.location.effective_geoprivacy})
              </Badge>
            )}
          </div>

          {specimen.location.information_withheld && (
            <div className="rounded-md border border-accent/30 bg-accent-soft px-3.5 py-2.5 text-xs text-accent">
              <strong>Geoprivacy Notice:</strong> {specimen.location.information_withheld}{" "}
              {specimen.location.data_generalizations}
            </div>
          )}

          <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div>
              <dt className="text-xs uppercase text-ink-muted">Country</dt>
              <dd className="mt-0.5 font-medium">{specimen.location.country || "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-ink-muted">State / Province</dt>
              <dd className="mt-0.5 font-medium">{specimen.location.state_province || "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-ink-muted">County</dt>
              <dd className="mt-0.5 font-medium">{specimen.location.county || "—"}</dd>
            </div>
            <div>
              <dt className="text-xs uppercase text-ink-muted">Elevation</dt>
              <dd className="mt-0.5 font-mono text-xs">{elevation || "—"}</dd>
            </div>
            <div className="col-span-2">
              <dt className="text-xs uppercase text-ink-muted">Coordinates ({specimen.location.geodetic_datum})</dt>
              <dd className="mt-0.5 font-mono text-xs">
                {specimen.location.decimal_latitude != null && specimen.location.decimal_longitude != null
                  ? `${specimen.location.decimal_latitude.toFixed(5)}, ${specimen.location.decimal_longitude.toFixed(5)}${
                      specimen.location.coordinate_uncertainty_m
                        ? ` (±${specimen.location.coordinate_uncertainty_m.toLocaleString()} m)`
                        : ""
                    }`
                  : "Withheld / Unrecorded"}
              </dd>
            </div>
          </dl>

          {specimen.location.locality && (
            <div className="text-sm">
              <span className="text-xs uppercase text-ink-muted block">Locality Description</span>
              <p className="mt-0.5 text-ink">{specimen.location.locality}</p>
            </div>
          )}

          {mapFeatures.length > 0 && (
            <SpecimenMap
              features={mapFeatures}
              herbariumSlug={herbarium.slug}
              heightClass="h-[280px]"
              singleUncertaintyMeters={
                specimen.location.coordinates_obscured
                  ? specimen.location.coordinate_uncertainty_m || 10000
                  : specimen.location.coordinate_uncertainty_m
              }
            />
          )}
        </section>

        {/* Determination History */}
        <section className="lg:col-span-5 rounded-lg border border-border bg-surface p-6 space-y-4">
          <h2 className="flex items-center gap-2 font-serif text-xl font-semibold">
            <History className="h-4 w-4 text-primary" />
            Determination History
          </h2>

          {!specimen.determinations || specimen.determinations.length === 0 ? (
            <p className="text-sm text-ink-muted">No formal determinations recorded yet (indet.).</p>
          ) : (
            <ol className="relative border-l border-border ml-2 space-y-5 pl-5">
              {specimen.determinations.map((det) => (
                <li key={det.id} className="relative">
                  <span
                    className={`absolute -left-[25px] top-1.5 h-2.5 w-2.5 rounded-full border-2 border-surface ${
                      det.is_current ? "bg-primary" : "bg-border"
                    }`}
                  />
                  <div className="flex flex-wrap items-center gap-2">
                    <ScientificName
                      name={det.taxon?.scientific_name}
                      authorship={det.taxon?.scientific_name_authorship}
                      qualifier={det.identification_qualifier}
                      className="text-base"
                    />
                    {det.is_current && <Badge tone="primary">Current</Badge>}
                  </div>
                  <div className="mt-1 text-xs text-ink-muted">
                    Det. {det.identified_by || "Unknown"}
                    {det.date_identified ? ` · ${det.date_identified}` : ""}
                  </div>
                  {det.identification_remarks && (
                    <p className="mt-1.5 text-xs text-ink-muted italic">
                      “{det.identification_remarks}”
                    </p>
                  )}
                </li>
              ))}
            </ol>
          )}
        </section>
      </div>
    </div>
  );
}
