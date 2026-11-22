<?php

namespace App\Domain\Specimens;

use App\Models\Determination;
use App\Models\Specimen;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeterminationService
{
    /**
     * Records a new determination (or unidentified/indet. state) for a specimen
     * and atomically makes it the current determination.
     *
     * @param  array{
     *   taxon_id?: ?string,
     *   identified_by?: ?string,
     *   date_identified?: ?string,
     *   identification_qualifier?: ?string,
     *   identification_remarks?: ?string
     * }  $attributes
     */
    public function record(Specimen $specimen, array $attributes, ?User $actor = null): Determination
    {
        return DB::transaction(function () use ($specimen, $attributes, $actor) {
            $specimen->determinations()->where('is_current', true)->update(['is_current' => false]);

            $determination = Determination::create([
                'specimen_id' => $specimen->id,
                'taxon_id' => $attributes['taxon_id'] ?? null,
                'identified_by' => $attributes['identified_by'] ?? $actor?->name,
                'identified_by_user_id' => $actor?->id,
                'date_identified' => $attributes['date_identified'] ?? now()->toDateString(),
                'identification_qualifier' => $attributes['identification_qualifier'] ?? null,
                'identification_remarks' => $attributes['identification_remarks'] ?? null,
                'is_current' => true,
            ]);

            $specimen->update(['current_determination_id' => $determination->id]);

            return $determination->load('taxon');
        });
    }
}
