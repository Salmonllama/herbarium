<?php

namespace App\Http\Resources;

use App\Models\Taxon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Taxon */
class TaxonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'scientific_name' => $this->scientific_name,
            'scientific_name_authorship' => $this->scientific_name_authorship,
            'taxon_rank' => $this->taxon_rank->value,
            'kingdom' => $this->kingdom,
            'phylum' => $this->phylum,
            'taxon_class' => $this->taxon_class,
            'taxon_order' => $this->taxon_order,
            'family' => $this->family,
            'genus' => $this->genus,
            'specific_epithet' => $this->specific_epithet,
            'infraspecific_rank' => $this->infraspecific_rank,
            'infraspecific_epithet' => $this->infraspecific_epithet,
            'common_names' => $this->common_names ?? [],
            'description_markdown' => $this->description_markdown,
            'morphology_notes' => $this->morphology_notes,
            'ecology_notes' => $this->ecology_notes,
            'conservation_status' => $this->conservation_status,
            'external_source' => $this->external_source,
            'external_taxon_id' => $this->external_taxon_id,
            'is_sensitive_location' => $this->is_sensitive_location,
            'specimens_count' => $this->when(isset($this->specimens_count), $this->specimens_count),
            'media' => $this->when(
                $this->relationLoaded('media') && class_exists(MediaResource::class),
                fn () => MediaResource::collection($this->media),
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
