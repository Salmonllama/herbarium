export interface User {
  id: number;
  name: string;
  email: string;
  orcid_id: string | null;
  institution: string | null;
  is_instance_admin: boolean;
  email_verified_at: string | null;
  created_at: string | null;
}

export interface UserHerbariumSummary {
  id: string;
  slug: string;
  name: string;
  institution_code: string | null;
  role: "owner" | "curator" | "contributor" | "viewer";
  can_view_exact_coordinates: boolean;
}

export interface InstanceConfig {
  app_name: string;
  registration_mode: "open" | "invite_only";
  default_herbarium_slug: string | null;
  custom_domain_herbarium: Herbarium | null;
  auth_providers: Array<{
    id: string;
    label: string;
    redirect_url: string;
  }>;
}

export interface Herbarium {
  id: string;
  slug: string;
  name: string;
  institution_code: string | null;
  custom_domain: string | null;
  description: string | null;
  contact_email: string | null;
  website_url: string | null;
  visibility: "public" | "unlisted" | "private";
  id_prefix: string;
  id_separator: string;
  id_padding: number;
  id_next_sequence?: number;
  next_catalog_number_preview?: string;
  default_geoprivacy: "exact" | "obscured" | "hidden";
  default_license: string;
  current_user_role: "owner" | "curator" | "contributor" | "viewer" | null;
  can_view_exact_coordinates: boolean;
  counts?: {
    specimens: number | null;
    published_specimens: number | null;
    taxa: number | null;
  };
  created_at: string | null;
  updated_at: string | null;
}

export interface CommonName {
  name: string;
  language?: string;
}

export interface MediaItem {
  id: string;
  herbarium_id: string;
  category: "sheet_scan" | "field_photo" | "closeup" | "label" | "reference";
  width: number | null;
  height: number | null;
  mime_type: string;
  size_bytes: number;
  caption: string | null;
  photographer: string | null;
  license_code: string | null;
  sort_order: number;
  processing_status: "pending" | "ready" | "failed";
  thumb_url: string | null;
  public_url: string | null;
  created_at: string | null;
}

export interface Taxon {
  id: string;
  herbarium_id: string;
  scientific_name: string;
  scientific_name_authorship: string | null;
  taxon_rank: string;
  kingdom: string | null;
  phylum: string | null;
  taxon_class: string | null;
  taxon_order: string | null;
  family: string | null;
  genus: string | null;
  specific_epithet: string | null;
  infraspecific_rank: string | null;
  infraspecific_epithet: string | null;
  common_names: CommonName[];
  description_markdown: string | null;
  morphology_notes: string | null;
  ecology_notes: string | null;
  conservation_status: string | null;
  external_source: "manual" | "gbif" | "powo";
  external_taxon_id: string | null;
  is_sensitive_location: boolean;
  specimens_count?: number;
  media?: MediaItem[];
  created_at: string | null;
  updated_at: string | null;
}

export interface Determination {
  id: string;
  specimen_id: string;
  taxon_id: string | null;
  taxon?: Taxon | null;
  identified_by: string | null;
  date_identified: string | null;
  identification_qualifier: string | null;
  identification_remarks: string | null;
  is_current: boolean;
  created_at: string | null;
}

export interface SpecimenLocation {
  country_code: string | null;
  country: string | null;
  state_province: string | null;
  county: string | null;
  municipality: string | null;
  locality: string | null;
  elevation_min_m: number | null;
  elevation_max_m: number | null;
  decimal_latitude: number | null;
  decimal_longitude: number | null;
  coordinate_uncertainty_m: number | null;
  geodetic_datum: string;
  geoprivacy: "inherit" | "exact" | "obscured" | "hidden";
  effective_geoprivacy: "exact" | "obscured" | "hidden";
  coordinates_obscured: boolean;
  information_withheld: string | null;
  data_generalizations: string | null;
}

export interface Specimen {
  id: string;
  herbarium_id: string;
  herbarium_slug: string | null;
  catalog_number: string;
  field_number: string | null;
  permalink: string | null;
  visibility: "draft" | "private" | "published";
  curation_status: "unmounted" | "mounted" | "verified" | "archived" | "missing";
  current_determination?: Determination | null;
  determinations?: Determination[];
  recorded_by: string | null;
  recorded_by_ids: string[];
  event_date: string | null;
  event_date_end: string | null;
  verbatim_event_date: string | null;
  habitat: string | null;
  substrate: string | null;
  associated_taxa: string | null;
  reproductive_condition: string | null;
  preparations: string | null;
  type_status: string | null;
  occurrence_remarks: string | null;
  storage?: {
    room: string | null;
    cabinet: string | null;
    shelf: string | null;
    folder: string | null;
    notes: string | null;
  };
  location: SpecimenLocation;
  media?: MediaItem[];
  created_by: number | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PaginatedResponse<T> {
  data: T[];
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface SpecimenFacets {
  families: Array<{ value: string; count: number }>;
  genera: Array<{ value: string; count: number }>;
  countries: Array<{ value: string; count: number }>;
  preparations: Array<{ value: string; count: number }>;
  type_statuses: Array<{ value: string; count: number }>;
}

export interface TaxonomicTreeFamily {
  family: string;
  specimen_count: number;
  taxa_count: number;
  genera: Array<{
    genus: string;
    specimen_count: number;
    taxa: Array<{
      id: string;
      scientific_name: string;
      scientific_name_authorship: string | null;
      taxon_rank: string;
      specimen_count: number;
    }>;
  }>;
}

export interface GeoJsonFeature {
  type: "Feature";
  geometry: {
    type: "Point";
    coordinates: [number, number]; // [lng, lat]
  };
  properties: {
    id: string;
    catalog_number: string;
    scientific_name: string;
    family: string | null;
    recorded_by: string | null;
    event_date: string | null;
    country: string | null;
    state_province: string | null;
    coordinates_obscured: boolean;
    coordinate_uncertainty_m: number | null;
    thumb_url: string | null;
  };
}

export interface GeoJsonCollection {
  type: "FeatureCollection";
  count: number;
  features: GeoJsonFeature[];
}

export interface TaxonomyLookupResult {
  external_source: "gbif" | "powo";
  external_taxon_id: string;
  scientific_name: string;
  scientific_name_authorship: string | null;
  taxon_rank: string;
  kingdom: string | null;
  phylum: string | null;
  taxon_class: string | null;
  taxon_order: string | null;
  family: string | null;
  genus: string | null;
  specific_epithet: string | null;
  infraspecific_rank: string | null;
  infraspecific_epithet: string | null;
  common_names: CommonName[];
}

export interface HerbariumMember {
  id: string;
  herbarium_id: string;
  role: "owner" | "curator" | "contributor" | "viewer";
  can_view_exact_coordinates: boolean;
  user: User;
  created_at: string | null;
}

export interface HerbariumInvitation {
  id: string;
  herbarium_id: string;
  email: string;
  role: "owner" | "curator" | "contributor" | "viewer";
  can_view_exact_coordinates: boolean;
  token?: string;
  is_pending: boolean;
  accepted_at: string | null;
  expires_at: string;
  herbarium?: Herbarium;
}

export interface ApiTokenItem {
  id: number;
  name: string;
  abilities: string[];
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string | null;
}

export interface ImportExportJobItem {
  id: string;
  type: "csv_import" | "csv_export" | "dwca_export" | "labels_pdf";
  status: string;
  download_filename: string | null;
  has_download: boolean;
  summary: Record<string, unknown> | null;
  error_message: string | null;
  completed_at: string | null;
  created_at: string | null;
}
