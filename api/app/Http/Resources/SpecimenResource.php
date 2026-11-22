<?php

namespace App\Http\Resources;

use App\Domain\Geoprivacy\GeoprivacyService;
use App\Models\Specimen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Specimen */
class SpecimenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user('sanctum');
        $herbarium = $this->herbarium;
        $isMember = $user && ($user->is_instance_admin || $user->membershipFor($herbarium) !== null);
        $canViewExact = $user && (
            $user->canViewExactCoordinatesIn($herbarium)
            || ($isMember && $this->created_by === $user->id)
        );

        $location = app(GeoprivacyService::class)->presentLocation($this->resource, (bool) $canViewExact);

        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'herbarium_slug' => $herbarium?->slug,
            'catalog_number' => $this->catalog_number,
            'field_number' => $this->field_number,
            'permalink' => $herbarium
                ? rtrim((string) config('app.url'), '/')."/h/{$herbarium->slug}/specimens/".rawurlencode($this->catalog_number)
                : null,
            'visibility' => $this->visibility->value,
            'curation_status' => $this->curation_status->value,

            // Determination & taxonomy
            'current_determination' => new DeterminationResource($this->whenLoaded('currentDetermination')),
            'determinations' => DeterminationResource::collection($this->whenLoaded('determinations')),

            // Darwin Core field metadata
            'recorded_by' => $this->recorded_by,
            'recorded_by_ids' => $this->recorded_by_ids ?? [],
            'event_date' => $this->event_date?->format('Y-m-d'),
            'event_date_end' => $this->event_date_end?->format('Y-m-d'),
            'verbatim_event_date' => $this->verbatim_event_date,
            'habitat' => $this->habitat,
            'substrate' => $this->substrate,
            'associated_taxa' => $this->associated_taxa,
            'reproductive_condition' => $this->reproductive_condition,
            'preparations' => $this->preparations,
            'type_status' => $this->type_status,
            'occurrence_remarks' => $this->occurrence_remarks,

            // Physical storage tracking (visible to herbarium members)
            'storage' => $this->when($isMember, fn () => [
                'room' => $this->storage_room,
                'cabinet' => $this->storage_cabinet,
                'shelf' => $this->storage_shelf,
                'folder' => $this->storage_folder,
                'notes' => $this->storage_notes,
            ]),

            // Location (geoprivacy-enforced)
            'location' => [
                'country_code' => $this->country_code,
                'country' => $this->country,
                'state_province' => $this->state_province,
                'county' => $this->county,
                'municipality' => $this->municipality,
                'locality' => $location['locality'],
                'elevation_min_m' => $this->elevation_min_m,
                'elevation_max_m' => $this->elevation_max_m,
                'decimal_latitude' => $location['decimal_latitude'],
                'decimal_longitude' => $location['decimal_longitude'],
                'coordinate_uncertainty_m' => $location['coordinate_uncertainty_m'],
                'geodetic_datum' => $this->geodetic_datum,
                'geoprivacy' => $this->geoprivacy->value,
                'effective_geoprivacy' => $location['effective_geoprivacy'],
                'coordinates_obscured' => $location['coordinates_obscured'],
                'information_withheld' => $location['information_withheld'],
                'data_generalizations' => $location['data_generalizations'],
            ],

            'media' => $this->when(
                $this->relationLoaded('media') && class_exists(MediaResource::class),
                fn () => MediaResource::collection($this->media),
            ),

            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
