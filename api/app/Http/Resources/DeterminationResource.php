<?php

namespace App\Http\Resources;

use App\Models\Determination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Determination */
class DeterminationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'specimen_id' => $this->specimen_id,
            'taxon_id' => $this->taxon_id,
            'taxon' => new TaxonResource($this->whenLoaded('taxon')),
            'identified_by' => $this->identified_by,
            'date_identified' => $this->date_identified?->format('Y-m-d'),
            'identification_qualifier' => $this->identification_qualifier,
            'identification_remarks' => $this->identification_remarks,
            'is_current' => $this->is_current,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
