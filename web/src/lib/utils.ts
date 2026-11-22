import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import type { Herbarium, Specimen } from "./api/types";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatElevation(min?: number | null, max?: number | null): string | null {
  if (min != null && max != null && min !== max) {
    return `${min}–${max} m`;
  }
  if (min != null) {
    return `${min} m`;
  }
  if (max != null) {
    return `${max} m`;
  }
  return null;
}

export function formatCitation(herbarium: Herbarium, specimen: Specimen): string {
  const taxon = specimen.current_determination?.taxon;
  const sciName = taxon?.scientific_name || "Indeterminata";
  const collector = specimen.recorded_by || "Unknown collector";
  const date = specimen.event_date || specimen.verbatim_event_date || "s.d.";
  const inst = herbarium.institution_code ? ` (${herbarium.institution_code})` : "";
  const link = specimen.permalink || `/h/${herbarium.slug}/specimens/${encodeURIComponent(specimen.catalog_number)}`;

  return `${herbarium.name}${inst}. Specimen ${specimen.catalog_number}: ${sciName}. Leg. ${collector}, ${date}. Available at: ${link}`;
}

export function formatBibtex(herbarium: Herbarium, specimen: Specimen): string {
  const key = specimen.catalog_number.replace(/[^a-zA-Z0-9]/g, "_");
  const taxon = specimen.current_determination?.taxon;
  const sciName = taxon?.scientific_name || "Indeterminata";
  const year = specimen.event_date ? specimen.event_date.slice(0, 4) : "n.d.";
  const link = specimen.permalink || "";

  return `@misc{herbarium_${key},
  title = {${sciName} (${specimen.catalog_number})},
  author = {${specimen.recorded_by || herbarium.name}},
  howpublished = {${herbarium.name}},
  year = {${year}},
  url = {${link}}
}`;
}
