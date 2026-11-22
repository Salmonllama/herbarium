"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import {
  Camera,
  Check,
  Compass,
  History,
  Loader2,
  MapPin,
  Plus,
  Search,
  Smartphone,
  Sparkles,
  Trash2,
} from "lucide-react";
import { apiFetch, ApiError } from "@/lib/api/client";
import type {
  Herbarium,
  MediaItem,
  PaginatedResponse,
  Specimen,
  Taxon,
  TaxonomyLookupResult,
} from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input, Select, Textarea } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { ScientificName } from "@/components/botanical/scientific-name";

interface ExifHints {
  captured_at: string | null;
  latitude: number | null;
  longitude: number | null;
  camera: string | null;
}

export function SpecimenForm({
  herbarium,
  initialSpecimen = null,
}: {
  herbarium: Herbarium;
  initialSpecimen?: Specimen | null;
}) {
  const router = useRouter();
  const isEditing = initialSpecimen !== null;
  const canCurate =
    herbarium.current_user_role === "owner" || herbarium.current_user_role === "curator";

  const [fieldCaptureMode, setFieldCaptureMode] = useState(false);
  const [taxa, setTaxa] = useState<Taxon[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [gpsStatus, setGpsStatus] = useState<string | null>(null);

  // Specimen form state
  const [catalogNumber, setCatalogNumber] = useState(initialSpecimen?.catalog_number || "");
  const [fieldNumber, setFieldNumber] = useState(initialSpecimen?.field_number || "");
  const [taxonId, setTaxonId] = useState(initialSpecimen?.current_determination?.taxon_id || "");
  const [qualifier, setQualifier] = useState(
    initialSpecimen?.current_determination?.identification_qualifier || "",
  );
  const [visibility, setVisibility] = useState<string>(initialSpecimen?.visibility || "draft");
  const [curationStatus, setCurationStatus] = useState<string>(
    initialSpecimen?.curation_status || "unmounted",
  );

  // Darwin Core metadata
  const [recordedBy, setRecordedBy] = useState(initialSpecimen?.recorded_by || "");
  const [eventDate, setEventDate] = useState(initialSpecimen?.event_date || "");
  const [verbatimDate, setVerbatimDate] = useState(initialSpecimen?.verbatim_event_date || "");
  const [habitat, setHabitat] = useState(initialSpecimen?.habitat || "");
  const [substrate, setSubstrate] = useState(initialSpecimen?.substrate || "");
  const [associatedTaxa, setAssociatedTaxa] = useState(initialSpecimen?.associated_taxa || "");
  const [reproductiveCondition, setReproductiveCondition] = useState(
    initialSpecimen?.reproductive_condition || "",
  );
  const [preparations, setPreparations] = useState(initialSpecimen?.preparations || "pressed sheet");
  const [typeStatus, setTypeStatus] = useState(initialSpecimen?.type_status || "");
  const [remarks, setRemarks] = useState(initialSpecimen?.occurrence_remarks || "");

  // Location & Geoprivacy
  const [country, setCountry] = useState(initialSpecimen?.location.country || "");
  const [countryCode, setCountryCode] = useState(initialSpecimen?.location.country_code || "");
  const [stateProvince, setStateProvince] = useState(initialSpecimen?.location.state_province || "");
  const [county, setCounty] = useState(initialSpecimen?.location.county || "");
  const [locality, setLocality] = useState(initialSpecimen?.location.locality || "");
  const [elevationMin, setElevationMin] = useState(
    initialSpecimen?.location.elevation_min_m != null
      ? String(initialSpecimen.location.elevation_min_m)
      : "",
  );
  const [lat, setLat] = useState(
    initialSpecimen?.location.decimal_latitude != null
      ? String(initialSpecimen.location.decimal_latitude)
      : "",
  );
  const [lng, setLng] = useState(
    initialSpecimen?.location.decimal_longitude != null
      ? String(initialSpecimen.location.decimal_longitude)
      : "",
  );
  const [uncertaintyM, setUncertaintyM] = useState(
    initialSpecimen?.location.coordinate_uncertainty_m != null
      ? String(initialSpecimen.location.coordinate_uncertainty_m)
      : "",
  );
  const [geoprivacy, setGeoprivacy] = useState<string>(
    initialSpecimen?.location.geoprivacy || "inherit",
  );

  // Physical storage
  const [storageRoom, setStorageRoom] = useState(initialSpecimen?.storage?.room || "");
  const [storageCabinet, setStorageCabinet] = useState(initialSpecimen?.storage?.cabinet || "");
  const [storageShelf, setStorageShelf] = useState(initialSpecimen?.storage?.shelf || "");
  const [storageFolder, setStorageFolder] = useState(initialSpecimen?.storage?.folder || "");

  // Media upload & EXIF hints
  const [media, setMedia] = useState<MediaItem[]>(initialSpecimen?.media || []);
  const [uploadingMedia, setUploadingMedia] = useState(false);
  const [uploadCategory, setUploadCategory] = useState("sheet_scan");
  const [exifHints, setExifHints] = useState<ExifHints | null>(null);

  // Quick GBIF lookup inline
  const [lookupOpen, setLookupOpen] = useState(false);
  const [lookupQuery, setLookupQuery] = useState("");
  const [lookupResults, setLookupResults] = useState<TaxonomyLookupResult[]>([]);
  const [lookingUp, setLookingUp] = useState(false);

  // Formal Re-determination form (edit mode)
  const [detTaxonId, setDetTaxonId] = useState("");
  const [detBy, setDetBy] = useState("");
  const [detQualifier, setDetQualifier] = useState("");
  const [detRemarks, setDetRemarks] = useState("");
  const [determinations, setDeterminations] = useState(initialSpecimen?.determinations || []);

  useEffect(() => {
    apiFetch<PaginatedResponse<Taxon>>(`/api/v1/herbaria/${herbarium.slug}/taxa?per_page=100`)
      .then((res) => setTaxa(res.data))
      .catch(() => {});
  }, [herbarium.slug]);

  function captureBrowserGps() {
    if (typeof navigator === "undefined" || !navigator.geolocation) {
      setGpsStatus("Geolocation is not supported by this browser.");
      return;
    }
    setGpsStatus("Acquiring GPS fix…");
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLat(pos.coords.latitude.toFixed(6));
        setLng(pos.coords.longitude.toFixed(6));
        setUncertaintyM(String(Math.round(pos.coords.accuracy)));
        setGpsStatus(`GPS acquired (±${Math.round(pos.coords.accuracy)} m)`);
      },
      (err) => {
        setGpsStatus(`GPS unavailable: ${err.message}`);
      },
      { enableHighAccuracy: true, timeout: 15000 },
    );
  }

  async function handleQuickLookup() {
    if (lookupQuery.trim().length < 2) return;
    setLookingUp(true);
    try {
      const res = await apiFetch<{ data: TaxonomyLookupResult[] }>(
        `/api/v1/taxonomy/lookup?q=${encodeURIComponent(lookupQuery.trim())}&source=gbif`,
      );
      setLookupResults(res.data);
    } catch {
      setLookupResults([]);
    } finally {
      setLookingUp(false);
    }
  }

  async function importAndSelectTaxon(item: TaxonomyLookupResult) {
    try {
      const res = await apiFetch<{ data: Taxon }>(`/api/v1/herbaria/${herbarium.slug}/taxa`, {
        method: "POST",
        json: item,
      });
      setTaxa((prev) => [res.data, ...prev]);
      setTaxonId(res.data.id);
      setLookupOpen(false);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Could not add taxon.");
    }
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);

    const payload: Record<string, unknown> = {
      catalog_number: catalogNumber.trim() || null,
      field_number: fieldNumber.trim() || null,
      taxon_id: taxonId || null,
      identification_qualifier: qualifier.trim() || null,
      curation_status: curationStatus,
      recorded_by: recordedBy.trim() || null,
      event_date: eventDate || null,
      verbatim_event_date: verbatimDate.trim() || null,
      habitat: habitat.trim() || null,
      substrate: substrate.trim() || null,
      associated_taxa: associatedTaxa.trim() || null,
      reproductive_condition: reproductiveCondition.trim() || null,
      preparations: preparations.trim() || "pressed sheet",
      type_status: typeStatus.trim() || null,
      occurrence_remarks: remarks.trim() || null,
      country: country.trim() || null,
      country_code: countryCode.trim().toUpperCase() || null,
      state_province: stateProvince.trim() || null,
      county: county.trim() || null,
      locality: locality.trim() || null,
      elevation_min_m: elevationMin !== "" ? Number(elevationMin) : null,
      decimal_latitude: lat !== "" ? Number(lat) : null,
      decimal_longitude: lng !== "" ? Number(lng) : null,
      coordinate_uncertainty_m: uncertaintyM !== "" ? Number(uncertaintyM) : null,
      geoprivacy,
      storage_room: storageRoom.trim() || null,
      storage_cabinet: storageCabinet.trim() || null,
      storage_shelf: storageShelf.trim() || null,
      storage_folder: storageFolder.trim() || null,
    };

    if (canCurate) {
      payload.visibility = visibility;
    }

    try {
      if (isEditing && initialSpecimen) {
        const res = await apiFetch<{ data: Specimen }>(
          `/api/v1/herbaria/${herbarium.slug}/specimens/${encodeURIComponent(
            initialSpecimen.catalog_number,
          )}`,
          { method: "PATCH", json: payload },
        );
        router.push(
          `/app/${herbarium.slug}/specimens/${encodeURIComponent(res.data.catalog_number)}/edit`,
        );
        router.refresh();
      } else {
        const res = await apiFetch<{ data: Specimen }>(
          `/api/v1/herbaria/${herbarium.slug}/specimens`,
          { method: "POST", json: payload },
        );
        router.push(
          `/app/${herbarium.slug}/specimens/${encodeURIComponent(res.data.catalog_number)}/edit`,
        );
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to save specimen.");
    } finally {
      setSaving(false);
    }
  }

  async function handleFileUpload(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file || !initialSpecimen) return;

    setUploadingMedia(true);
    setError(null);
    const formData = new FormData();
    formData.append("file", file);
    formData.append("category", uploadCategory);

    try {
      const res = await apiFetch<{ data: MediaItem; exif_hints: ExifHints }>(
        `/api/v1/herbaria/${herbarium.slug}/specimens/${encodeURIComponent(
          initialSpecimen.catalog_number,
        )}/media`,
        { method: "POST", body: formData },
      );
      setMedia((prev) => [...prev, res.data]);
      if (res.exif_hints && (res.exif_hints.latitude != null || res.exif_hints.captured_at)) {
        setExifHints(res.exif_hints);
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Image upload failed.");
    } finally {
      setUploadingMedia(false);
      e.target.value = "";
    }
  }

  async function handleDeleteMedia(mediaId: string) {
    try {
      await apiFetch(`/api/v1/herbaria/${herbarium.slug}/media/${mediaId}`, {
        method: "DELETE",
      });
      setMedia((prev) => prev.filter((m) => m.id !== mediaId));
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to delete media.");
    }
  }

  async function handleAddDetermination(e: React.FormEvent) {
    e.preventDefault();
    if (!initialSpecimen) return;
    try {
      const res = await apiFetch<{ data: NonNullable<Specimen["current_determination"]> }>(
        `/api/v1/herbaria/${herbarium.slug}/specimens/${encodeURIComponent(
          initialSpecimen.catalog_number,
        )}/determinations`,
        {
          method: "POST",
          json: {
            taxon_id: detTaxonId || null,
            identified_by: detBy.trim() || null,
            identification_qualifier: detQualifier.trim() || null,
            identification_remarks: detRemarks.trim() || null,
          },
        },
      );
      setDeterminations((prev) => [
        res.data,
        ...prev.map((d) => ({ ...d, is_current: false })),
      ]);
      setTaxonId(res.data.taxon_id || "");
      setDetRemarks("");
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Failed to record determination.");
    }
  }

  return (
    <div className="space-y-6">
      {/* Mode Switcher */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="font-serif text-2xl sm:text-3xl font-semibold text-ink">
            {isEditing ? `Edit Specimen ${initialSpecimen.catalog_number}` : "Catalog New Specimen"}
          </h1>
          <p className="text-xs text-ink-muted mt-0.5">
            {!isEditing &&
              `Leave Catalog ID blank to auto-assign ${herbarium.next_catalog_number_preview || `${herbarium.id_prefix}-000001`}.`}
          </p>
        </div>

        <Button
          type="button"
          variant={fieldCaptureMode ? "primary" : "outline"}
          size="sm"
          onClick={() => setFieldCaptureMode((v) => !v)}
        >
          <Smartphone className="h-3.5 w-3.5" />
          {fieldCaptureMode ? "Field Capture Mode (Active)" : "Mobile Field Capture"}
        </Button>
      </div>

      {error && (
        <div className="rounded-md border border-danger/30 bg-danger-soft px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* EXIF Autofill Banner */}
      {exifHints && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-xs">
          <div className="flex items-center gap-2 text-ink">
            <Sparkles className="h-4 w-4 text-primary shrink-0" />
            <span>
              <strong>Photo EXIF detected:</strong>{" "}
              {exifHints.captured_at ? `Date ${exifHints.captured_at}` : ""}
              {exifHints.captured_at && exifHints.latitude != null ? " · " : ""}
              {exifHints.latitude != null && exifHints.longitude != null
                ? `GPS ${exifHints.latitude}, ${exifHints.longitude}`
                : ""}
            </span>
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              size="sm"
              onClick={() => {
                if (exifHints.captured_at) setEventDate(exifHints.captured_at);
                if (exifHints.latitude != null) setLat(String(exifHints.latitude));
                if (exifHints.longitude != null) setLng(String(exifHints.longitude));
                setExifHints(null);
              }}
            >
              <Check className="h-3.5 w-3.5" /> Apply to Specimen
            </Button>
            <Button type="button" variant="ghost" size="sm" onClick={() => setExifHints(null)}>
              Dismiss
            </Button>
          </div>
        </div>
      )}

      {/* Media Section (when editing an existing specimen) */}
      {isEditing && (
        <section className="rounded-lg border border-border bg-surface p-5 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 className="font-serif text-lg font-semibold text-ink">
                Specimen Photographs &amp; Scans
              </h2>
              <p className="text-xs text-ink-muted">
                EXIF GPS is read for optional autofill and stripped automatically from public WebP views.
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Select
                value={uploadCategory}
                onChange={(e) => setUploadCategory(e.target.value)}
                className="w-36 h-8 text-xs"
              >
                <option value="sheet_scan">Pressed Sheet</option>
                <option value="field_photo">Field Photo</option>
                <option value="closeup">Close-up</option>
                <option value="label">Sheet Label</option>
              </Select>
              <label className="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground hover:bg-primary-hover">
                {uploadingMedia ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <Camera className="h-3.5 w-3.5" />
                )}
                <span>{uploadingMedia ? "Uploading…" : "Add Photo"}</span>
                <input
                  type="file"
                  accept="image/*"
                  capture={fieldCaptureMode ? "environment" : undefined}
                  onChange={handleFileUpload}
                  className="hidden"
                  disabled={uploadingMedia}
                />
              </label>
            </div>
          </div>

          {media.length > 0 && (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-5">
              {media.map((m) => (
                <div
                  key={m.id}
                  className="group relative overflow-hidden rounded-md border border-border bg-surface-muted"
                >
                  <div className="aspect-square">
                    {m.thumb_url ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={m.thumb_url} alt={m.caption || "Specimen photo"} className="h-full w-full object-cover" />
                    ) : (
                      <div className="flex h-full items-center justify-center text-xs text-ink-muted">
                        Processing…
                      </div>
                    )}
                  </div>
                  <div className="flex items-center justify-between px-2 py-1 text-[11px] bg-surface">
                    <span className="truncate text-ink-muted">{m.category.replace("_", " ")}</span>
                    <button
                      type="button"
                      onClick={() => handleDeleteMedia(m.id)}
                      className="text-danger hover:opacity-80 cursor-pointer"
                      title="Delete photo"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Main Specimen Form */}
      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Identification & Core IDs */}
        <section className="rounded-lg border border-border bg-surface p-5 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="font-serif text-lg font-semibold">Identification &amp; Accession</h2>
            {canCurate && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setLookupOpen((v) => !v)}
              >
                <Search className="h-3.5 w-3.5" />
                GBIF Quick-Add Taxon
              </Button>
            )}
          </div>

          {lookupOpen && (
            <div className="rounded-md border border-primary/25 bg-surface-muted/60 p-3.5 space-y-3">
              <div className="flex gap-2">
                <Input
                  value={lookupQuery}
                  onChange={(e) => setLookupQuery(e.target.value)}
                  placeholder="Search GBIF Backbone (e.g. Pseudotsuga menziesii)…"
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      handleQuickLookup();
                    }
                  }}
                />
                <Button type="button" size="sm" onClick={handleQuickLookup} disabled={lookingUp}>
                  {lookingUp ? "Searching…" : "Lookup"}
                </Button>
              </div>
              {lookupResults.length > 0 && (
                <ul className="divide-y divide-border rounded border border-border bg-surface max-h-48 overflow-y-auto">
                  {lookupResults.map((r) => (
                    <li
                      key={`${r.external_taxon_id}-${r.scientific_name}`}
                      className="flex items-center justify-between px-3 py-2 text-xs"
                    >
                      <div>
                        <ScientificName name={r.scientific_name} authorship={r.scientific_name_authorship} />
                        <span className="ml-2 text-ink-muted">{r.family}</span>
                      </div>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => importAndSelectTaxon(r)}
                      >
                        <Plus className="h-3 w-3" /> Add &amp; Select
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Catalog Number (optional override)</label>
              <Input
                value={catalogNumber}
                onChange={(e) => setCatalogNumber(e.target.value)}
                placeholder={herbarium.next_catalog_number_preview || "Auto-generated"}
                className="font-mono"
              />
            </div>

            <div className="space-y-1">
              <label className="block text-xs font-medium">Field / Collector Number</label>
              <Input
                value={fieldNumber}
                onChange={(e) => setFieldNumber(e.target.value)}
                placeholder="e.g. EV-2026-042"
                className="font-mono"
              />
            </div>

            <div className="space-y-1">
              <label className="block text-xs font-medium">Plant / Taxon</label>
              <Select value={taxonId} onChange={(e) => setTaxonId(e.target.value)}>
                <option value="">Unidentified (indet.)</option>
                {taxa.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.scientific_name} {t.family ? `(${t.family})` : ""}
                  </option>
                ))}
              </Select>
            </div>

            {!fieldCaptureMode && (
              <>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">ID Qualifier</label>
                  <Select value={qualifier} onChange={(e) => setQualifier(e.target.value)}>
                    <option value="">None (certain)</option>
                    <option value="cf.">cf. (compare with)</option>
                    <option value="aff.">aff. (affinity to)</option>
                    <option value="s.l.">s.l. (sensu lato)</option>
                    <option value="s.str.">s.str. (sensu stricto)</option>
                  </Select>
                </div>

                <div className="space-y-1">
                  <label className="block text-xs font-medium">Curation Status</label>
                  <Select value={curationStatus} onChange={(e) => setCurationStatus(e.target.value)}>
                    <option value="unmounted">Unmounted</option>
                    <option value="mounted">Mounted</option>
                    <option value="verified">Verified</option>
                    <option value="archived">Archived</option>
                    <option value="missing">Missing</option>
                  </Select>
                </div>

                {canCurate && (
                  <div className="space-y-1">
                    <label className="block text-xs font-medium">Visibility</label>
                    <Select value={visibility} onChange={(e) => setVisibility(e.target.value)}>
                      <option value="draft">Draft</option>
                      <option value="private">Private (Members Only)</option>
                      <option value="published">Published (Public Portal)</option>
                    </Select>
                  </div>
                )}
              </>
            )}
          </div>
        </section>

        {/* Location & GPS (Prominent in Mobile Field Capture!) */}
        <section className="rounded-lg border border-border bg-surface p-5 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 className="flex items-center gap-2 font-serif text-lg font-semibold">
              <MapPin className="h-4 w-4 text-primary" />
              Location &amp; Geoprivacy
            </h2>
            <Button type="button" variant="secondary" size="sm" onClick={captureBrowserGps}>
              <Compass className="h-3.5 w-3.5" />
              Use Current GPS
            </Button>
          </div>

          {gpsStatus && <p className="text-xs font-mono text-primary">{gpsStatus}</p>}

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Latitude (WGS84)</label>
              <Input
                type="number"
                step="any"
                value={lat}
                onChange={(e) => setLat(e.target.value)}
                placeholder="45.523064"
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Longitude (WGS84)</label>
              <Input
                type="number"
                step="any"
                value={lng}
                onChange={(e) => setLng(e.target.value)}
                placeholder="-122.676483"
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Uncertainty (m)</label>
              <Input
                type="number"
                value={uncertaintyM}
                onChange={(e) => setUncertaintyM(e.target.value)}
                placeholder="15"
                className="font-mono"
              />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Geoprivacy</label>
              <Select value={geoprivacy} onChange={(e) => setGeoprivacy(e.target.value)}>
                <option value="inherit">Inherit (Taxon / Herbarium)</option>
                <option value="exact">Exact (Public)</option>
                <option value="obscured">Obscured (~10 km grid)</option>
                <option value="hidden">Hidden (Withheld publicly)</option>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Country</label>
              <Input value={country} onChange={(e) => setCountry(e.target.value)} placeholder="United States" />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">State / Province</label>
              <Input value={stateProvince} onChange={(e) => setStateProvince(e.target.value)} placeholder="Oregon" />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">County</label>
              <Input value={county} onChange={(e) => setCounty(e.target.value)} placeholder="Hood River" />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Elevation (m)</label>
              <Input
                type="number"
                value={elevationMin}
                onChange={(e) => setElevationMin(e.target.value)}
                placeholder="420"
                className="font-mono"
              />
            </div>
          </div>

          <div className="space-y-1">
            <label className="block text-xs font-medium">Locality Description</label>
            <Textarea
              value={locality}
              onChange={(e) => setLocality(e.target.value)}
              placeholder="e.g. 2.1 km NE of summit trailhead along north-facing basalt scree…"
              rows={2}
            />
          </div>
        </section>

        {/* Collection Event & Ecology */}
        <section className="rounded-lg border border-border bg-surface p-5 space-y-4">
          <h2 className="font-serif text-lg font-semibold">Collection Event &amp; Ecology (Darwin Core)</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Collector (Recorded By)</label>
              <Input value={recordedBy} onChange={(e) => setRecordedBy(e.target.value)} placeholder="Dr. Elena Vance" />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Collection Date</label>
              <Input type="date" value={eventDate} onChange={(e) => setEventDate(e.target.value)} />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Phenology / Reproductive State</label>
              <Input
                value={reproductiveCondition}
                onChange={(e) => setReproductiveCondition(e.target.value)}
                placeholder="flowering, fruiting, vegetative…"
              />
            </div>
            {!fieldCaptureMode && (
              <>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">Verbatim Date (historical)</label>
                  <Input
                    value={verbatimDate}
                    onChange={(e) => setVerbatimDate(e.target.value)}
                    placeholder="Summer 1954"
                  />
                </div>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">Preparations</label>
                  <Input
                    value={preparations}
                    onChange={(e) => setPreparations(e.target.value)}
                    placeholder="pressed sheet, wet, seed packet, silica tissue"
                  />
                </div>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">Nomenclatural Type Status</label>
                  <Select value={typeStatus} onChange={(e) => setTypeStatus(e.target.value)}>
                    <option value="">Not a type</option>
                    <option value="Holotype">Holotype</option>
                    <option value="Isotype">Isotype</option>
                    <option value="Paratype">Paratype</option>
                    <option value="Lectotype">Lectotype</option>
                    <option value="Syntype">Syntype</option>
                    <option value="Neotype">Neotype</option>
                  </Select>
                </div>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">ISO Country Code (2-letter)</label>
                  <Input
                    value={countryCode}
                    maxLength={2}
                    onChange={(e) => setCountryCode(e.target.value.toUpperCase())}
                    placeholder="US"
                    className="font-mono uppercase"
                  />
                </div>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">Substrate</label>
                  <Input value={substrate} onChange={(e) => setSubstrate(e.target.value)} placeholder="Basalt loam, rotting log…" />
                </div>
                <div className="space-y-1">
                  <label className="block text-xs font-medium">Associated Taxa</label>
                  <Input value={associatedTaxa} onChange={(e) => setAssociatedTaxa(e.target.value)} placeholder="Pseudotsuga menziesii, Gaultheria shallon" />
                </div>
              </>
            )}
          </div>

          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1">
              <label className="block text-xs font-medium">Habitat</label>
              <Textarea value={habitat} onChange={(e) => setHabitat(e.target.value)} rows={2} />
            </div>
            <div className="space-y-1">
              <label className="block text-xs font-medium">Occurrence Remarks / Field Notes</label>
              <Textarea value={remarks} onChange={(e) => setRemarks(e.target.value)} rows={2} />
            </div>
          </div>
        </section>

        {/* Physical Storage Tracking */}
        {!fieldCaptureMode && (
          <section className="rounded-lg border border-border bg-surface p-5 space-y-4">
            <h2 className="font-serif text-lg font-semibold">Physical Archival Storage</h2>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              <div className="space-y-1">
                <label className="block text-xs font-medium">Room</label>
                <Input value={storageRoom} onChange={(e) => setStorageRoom(e.target.value)} placeholder="Wing 204" />
              </div>
              <div className="space-y-1">
                <label className="block text-xs font-medium">Cabinet</label>
                <Input value={storageCabinet} onChange={(e) => setStorageCabinet(e.target.value)} placeholder="CAB-04" className="font-mono" />
              </div>
              <div className="space-y-1">
                <label className="block text-xs font-medium">Shelf / Cubby</label>
                <Input value={storageShelf} onChange={(e) => setStorageShelf(e.target.value)} placeholder="S-02" className="font-mono" />
              </div>
              <div className="space-y-1">
                <label className="block text-xs font-medium">Folder</label>
                <Input value={storageFolder} onChange={(e) => setStorageFolder(e.target.value)} placeholder="Fagaceae-Quercus" />
              </div>
            </div>
          </section>
        )}

        <div className="flex items-center justify-end gap-3">
          <Button type="button" variant="outline" onClick={() => router.back()}>
            Cancel
          </Button>
          <Button type="submit" disabled={saving}>
            {saving ? "Saving…" : isEditing ? "Save Changes" : "Save Specimen"}
          </Button>
        </div>
      </form>

      {/* Formal Determination History (Edit Mode, Curators+) */}
      {isEditing && canCurate && (
        <section className="rounded-lg border border-border bg-surface p-5 space-y-4">
          <h2 className="flex items-center gap-2 font-serif text-lg font-semibold">
            <History className="h-4 w-4 text-primary" />
            Record Re-Identification / Annotation Slip
          </h2>

          <form onSubmit={handleAddDetermination} className="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <Select value={detTaxonId} onChange={(e) => setDetTaxonId(e.target.value)}>
              <option value="">Unidentified (indet.)</option>
              {taxa.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.scientific_name}
                </option>
              ))}
            </Select>
            <Input
              value={detBy}
              onChange={(e) => setDetBy(e.target.value)}
              placeholder="Determiner name"
            />
            <Select value={detQualifier} onChange={(e) => setDetQualifier(e.target.value)}>
              <option value="">No qualifier</option>
              <option value="cf.">cf.</option>
              <option value="aff.">aff.</option>
              <option value="s.l.">s.l.</option>
              <option value="s.str.">s.str.</option>
            </Select>
            <Button type="submit" variant="secondary">
              Add Determination
            </Button>
            <div className="sm:col-span-4">
              <Input
                value={detRemarks}
                onChange={(e) => setDetRemarks(e.target.value)}
                placeholder="Annotation remarks (optional)"
              />
            </div>
          </form>

          {determinations.length > 0 && (
            <ul className="divide-y divide-border pt-2">
              {determinations.map((d) => (
                <li key={d.id} className="py-2.5 flex flex-wrap items-center justify-between gap-2 text-sm">
                  <div>
                    <ScientificName
                      name={d.taxon?.scientific_name}
                      authorship={d.taxon?.scientific_name_authorship}
                      qualifier={d.identification_qualifier}
                    />
                    <span className="ml-2 text-xs text-ink-muted">
                      Det. {d.identified_by || "Unknown"} {d.date_identified || ""}
                    </span>
                    {d.identification_remarks && (
                      <p className="text-xs text-ink-muted italic">{d.identification_remarks}</p>
                    )}
                  </div>
                  {d.is_current && <Badge tone="primary">Current</Badge>}
                </li>
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  );
}
