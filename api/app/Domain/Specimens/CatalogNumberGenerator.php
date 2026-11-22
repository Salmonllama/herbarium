<?php

namespace App\Domain\Specimens;

use App\Models\Herbarium;
use App\Models\Specimen;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogNumberGenerator
{
    /**
     * Atomically allocates the next available catalog number for a herbarium,
     * skipping any identifiers already claimed by manual overrides or imports.
     */
    public function nextFor(Herbarium $herbarium): string
    {
        return DB::transaction(function () use ($herbarium) {
            /** @var Herbarium $locked */
            $locked = Herbarium::query()
                ->whereKey($herbarium->id)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence = max(1, (int) $locked->id_next_sequence);
            $padding = max(1, (int) $locked->id_padding);
            $prefix = (string) $locked->id_prefix;
            $separator = (string) $locked->id_separator;

            do {
                $candidate = $prefix.$separator.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
                $exists = Specimen::withTrashed()
                    ->where('herbarium_id', $locked->id)
                    ->where('catalog_number', $candidate)
                    ->exists();
                $sequence++;
            } while ($exists);

            $locked->update(['id_next_sequence' => $sequence]);

            return $candidate;
        });
    }

    public function assertUnique(Herbarium $herbarium, string $catalogNumber, ?string $ignoreSpecimenId = null): void
    {
        $query = Specimen::query()
            ->where('herbarium_id', $herbarium->id)
            ->where('catalog_number', $catalogNumber);

        if ($ignoreSpecimenId !== null) {
            $query->where('id', '!=', $ignoreSpecimenId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'catalog_number' => ["Catalog number '{$catalogNumber}' is already in use in this herbarium."],
            ]);
        }
    }
}
