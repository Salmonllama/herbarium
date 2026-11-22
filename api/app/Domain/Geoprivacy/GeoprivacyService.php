<?php

namespace App\Domain\Geoprivacy;

use App\Enums\GeoprivacyLevel;
use App\Models\Herbarium;
use App\Models\Specimen;
use App\Models\Taxon;

class GeoprivacyService
{
    /**
     * Resolve effective geoprivacy level:
     * specimen explicit override -> taxon.is_sensitive_location -> herbarium.default_geoprivacy
     */
    public function effectiveLevel(Specimen $specimen, ?Herbarium $herbarium = null, ?Taxon $taxon = null): GeoprivacyLevel
    {
        $specimenLevel = $specimen->geoprivacy instanceof GeoprivacyLevel
            ? $specimen->geoprivacy
            : GeoprivacyLevel::tryFrom((string) $specimen->geoprivacy) ?? GeoprivacyLevel::Inherit;

        if ($specimenLevel !== GeoprivacyLevel::Inherit) {
            return $specimenLevel;
        }

        $taxon ??= $specimen->currentDetermination?->taxon;
        if ($taxon && $taxon->is_sensitive_location) {
            return GeoprivacyLevel::Obscured;
        }

        $herbarium ??= $specimen->herbarium;
        if ($herbarium && $herbarium->default_geoprivacy instanceof GeoprivacyLevel) {
            return $herbarium->default_geoprivacy === GeoprivacyLevel::Inherit
                ? GeoprivacyLevel::Exact
                : $herbarium->default_geoprivacy;
        }

        return GeoprivacyLevel::Exact;
    }

    /**
     * Deterministic per-specimen obscured coordinates inside a ~10km grid cell.
     * Because the obscured point is seeded by the specimen UUID and persisted at write time,
     * repeated API requests cannot average jitter to recover the true location.
     *
     * @return array{latitude: float, longitude: float, uncertainty_m: int}
     */
    public function computeObscuredCoordinates(
        string $specimenUuid,
        float $latitude,
        float $longitude,
        ?int $exactUncertaintyMeters = null,
    ): array {
        $grid = (float) config('herbarium.geoprivacy.grid_degrees', 0.1);
        $minUncertainty = (int) config('herbarium.geoprivacy.min_obscured_uncertainty_m', 10000);

        $cellLat = floor($latitude / $grid) * $grid;
        $cellLng = floor($longitude / $grid) * $grid;

        $hash = hash('sha256', $specimenUuid.'|herbarium-geoprivacy-v1');
        $u1 = hexdec(substr($hash, 0, 8)) / 0xFFFFFFFF;
        $u2 = hexdec(substr($hash, 8, 8)) / 0xFFFFFFFF;

        // Keep point comfortably within cell interior [0.1, 0.9]
        $obscuredLat = max(-89.9, min(89.9, round($cellLat + (0.1 + 0.8 * $u1) * $grid, 5)));
        $obscuredLng = max(-179.9, min(179.9, round($cellLng + (0.1 + 0.8 * $u2) * $grid, 5)));

        return [
            'latitude' => $obscuredLat,
            'longitude' => $obscuredLng,
            'uncertainty_m' => max($minUncertainty, (int) ($exactUncertaintyMeters ?? 0)),
        ];
    }

    /**
     * Build the safe coordinate & locality payload for API responses and exports.
     *
     * @return array{
     *   effective_geoprivacy: string,
     *   coordinates_obscured: bool,
     *   decimal_latitude: ?float,
     *   decimal_longitude: ?float,
     *   coordinate_uncertainty_m: ?int,
     *   locality: ?string,
     *   information_withheld: ?string,
     *   data_generalizations: ?string
     * }
     */
    public function presentLocation(Specimen $specimen, bool $canViewExact): array
    {
        $effective = $this->effectiveLevel($specimen);
        $hasCoords = $specimen->decimal_latitude !== null && $specimen->decimal_longitude !== null;

        if ($canViewExact || $effective === GeoprivacyLevel::Exact) {
            return [
                'effective_geoprivacy' => $effective->value,
                'coordinates_obscured' => false,
                'decimal_latitude' => $hasCoords ? (float) $specimen->decimal_latitude : null,
                'decimal_longitude' => $hasCoords ? (float) $specimen->decimal_longitude : null,
                'coordinate_uncertainty_m' => $specimen->coordinate_uncertainty_m,
                'locality' => $specimen->locality,
                'information_withheld' => null,
                'data_generalizations' => null,
            ];
        }

        if ($effective === GeoprivacyLevel::Hidden) {
            return [
                'effective_geoprivacy' => GeoprivacyLevel::Hidden->value,
                'coordinates_obscured' => true,
                'decimal_latitude' => null,
                'decimal_longitude' => null,
                'coordinate_uncertainty_m' => null,
                'locality' => null,
                'information_withheld' => 'Exact coordinates and fine-grained locality withheld for conservation/privacy.',
                'data_generalizations' => 'Location generalized to administrative region.',
            ];
        }

        // Obscured (~10km grid)
        return [
            'effective_geoprivacy' => GeoprivacyLevel::Obscured->value,
            'coordinates_obscured' => true,
            'decimal_latitude' => $hasCoords && $specimen->obscured_latitude !== null ? (float) $specimen->obscured_latitude : null,
            'decimal_longitude' => $hasCoords && $specimen->obscured_longitude !== null ? (float) $specimen->obscured_longitude : null,
            'coordinate_uncertainty_m' => $hasCoords ? ($specimen->obscured_uncertainty_m ?? 10000) : null,
            'locality' => null,
            'information_withheld' => 'Exact locality description and precise coordinates obscured.',
            'data_generalizations' => 'Coordinates generalized to a 0.1 degree (~10 km) grid cell.',
        ];
    }
}
