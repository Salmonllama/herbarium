# Darwin Core (DwC) & GBIF Interoperability Guide

Herbarium aligns its specimen schema and exports with the **TDWG Darwin Core (DwC)** biodiversity standard so universities, museums, and researchers can publish directly to **GBIF**, **iDigBio**, **SEINet**, and institutional portals.

## Darwin Core Archive (DwC-A) Bundle Structure

Exports from `/api/v1/herbaria/{slug}/exports/dwca` (or the Workspace **Import & DwC-A** tab) produce a ZIP archive containing:

1. **`meta.xml`** — Archive descriptor declaring the `Occurrence` core (`occurrence.txt`) and `SimpleMultimedia` extension (`multimedia.txt`).
2. **`eml.xml`** — Ecological Metadata Language (EML 2.2.0) dataset citation, description, contact email, and intellectual rights license.
3. **`occurrence.txt`** — UTF-8 tab-delimited occurrence records.
4. **`multimedia.txt`** — UTF-8 tab-delimited links to EXIF-stripped public WebP specimen imagery.

## Term Mapping Reference

| Herbarium Field | Darwin Core Term | Notes |
|---|---|---|
| Permalink URL | `dwc:occurrenceID` | `https://{domain}/h/{slug}/specimens/{catalog_number}` |
| Constant | `dwc:basisOfRecord` | `PreservedSpecimen` |
| `herbaria.institution_code` | `dwc:institutionCode` | Index Herbariorum code (falls back to `id_prefix`) |
| `herbaria.slug` | `dwc:collectionCode` | Herbarium workspace slug |
| `specimens.catalog_number` | `dwc:catalogNumber` | Configurable prefix + atomic sequence or legacy override |
| `specimens.field_number` | `dwc:recordNumber` | Collector gathering number |
| `taxa.scientific_name` | `dwc:scientificName` | From current `Determination` |
| `taxa.scientific_name_authorship` | `dwc:scientificNameAuthorship` | Botanical authority |
| `taxa.kingdom` … `infraspecific_epithet` | `dwc:kingdom`, `phylum`, `class`, `order`, `family`, `genus`, `specificEpithet`, `infraspecificEpithet` | Full Linnaean hierarchy |
| `determinations.identified_by` | `dwc:identifiedBy` | Current determiner |
| `determinations.date_identified` | `dwc:dateIdentified` | ISO `YYYY-MM-DD` |
| `determinations.identification_qualifier` | `dwc:identificationQualifier` | `cf.`, `aff.`, `s.l.`, `s.str.` |
| `specimens.recorded_by` | `dwc:recordedBy` | Collector(s) |
| `specimens.event_date` / `verbatim_event_date` | `dwc:eventDate` / `dwc:verbatimEventDate` | Supports exact dates and historical verbatim dates |
| `specimens.habitat` / `reproductive_condition` / `preparations` / `type_status` | `dwc:habitat`, `reproductiveCondition`, `preparations`, `typeStatus` | Archival curation metadata |
| `specimens.country` … `locality` | `dwc:country`, `countryCode`, `stateProvince`, `county`, `municipality`, `locality` | `locality` redacted automatically when obscured/hidden |
| `specimens.elevation_min_m` / `elevation_max_m` | `dwc:minimumElevationInMeters` / `maximumElevationInMeters` | Meters above sea level |
| Effective coordinates | `dwc:decimalLatitude`, `decimalLongitude`, `coordinateUncertaintyInMeters`, `geodeticDatum` | Uses deterministic ~10 km `obscured_*` values for sensitive/obscured specimens |
| Geoprivacy compliance | `dwc:informationWithheld`, `dwc:dataGeneralizations` | Populated automatically whenever coordinates/locality are protected |

## Geoprivacy Guarantee in Exports

Public DwC-A and CSV exports **never** expose exact GPS coordinates, fine-grained locality descriptions, or raw EXIF GPS tags for specimens whose effective geoprivacy is `obscured` (explicit or inherited from a `Taxon` with `is_sensitive_location = true`) or `hidden`.
