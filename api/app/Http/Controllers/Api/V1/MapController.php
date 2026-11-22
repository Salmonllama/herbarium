<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\MediaUrlResolver;
use App\Domain\Geoprivacy\GeoprivacyService;
use App\Enums\GeoprivacyLevel;
use App\Http\Controllers\Controller;
use App\Models\Herbarium;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MapController extends Controller
{
    public function points(
        Request $request,
        Herbarium $herbarium,
        SpecimenController $specimenController,
        GeoprivacyService $geoprivacy,
        MediaUrlResolver $mediaUrls,
    ): JsonResponse {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Herbarium not found or private.'], 403);
        }

        $canViewExact = $user ? $user->canViewExactCoordinatesIn($herbarium) : false;

        $query = $specimenController->buildFilteredQuery($request, $herbarium, $user)
            ->whereNotNull('specimens.decimal_latitude')
            ->whereNotNull('specimens.decimal_longitude')
            ->with(['herbarium', 'currentDetermination.taxon', 'media'])
            ->limit(min(2000, max(1, (int) $request->input('limit', 1000))));

        $specimens = $query->get();

        $features = [];
        foreach ($specimens as $specimen) {
            $canSeeThisExact = $canViewExact || ($user && $specimen->created_by === $user->id && $user->membershipFor($herbarium) !== null);
            $effective = $geoprivacy->effectiveLevel($specimen, $herbarium);

            if (! $canSeeThisExact && $effective === GeoprivacyLevel::Hidden) {
                continue;
            }

            $loc = $geoprivacy->presentLocation($specimen, $canSeeThisExact);
            if ($loc['decimal_latitude'] === null || $loc['decimal_longitude'] === null) {
                continue;
            }

            $taxon = $specimen->currentDetermination?->taxon;
            $firstMedia = $specimen->media->first();

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$loc['decimal_longitude'], $loc['decimal_latitude']],
                ],
                'properties' => [
                    'id' => $specimen->id,
                    'catalog_number' => $specimen->catalog_number,
                    'scientific_name' => $taxon?->scientific_name ?? 'Unidentified (indet.)',
                    'family' => $taxon?->family,
                    'recorded_by' => $specimen->recorded_by,
                    'event_date' => $specimen->event_date?->format('Y-m-d'),
                    'country' => $specimen->country,
                    'state_province' => $specimen->state_province,
                    'coordinates_obscured' => $loc['coordinates_obscured'],
                    'coordinate_uncertainty_m' => $loc['coordinate_uncertainty_m'],
                    'thumb_url' => $firstMedia ? $mediaUrls->url($firstMedia, 'thumb') : null,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'count' => count($features),
            'features' => $features,
        ]);
    }
}
