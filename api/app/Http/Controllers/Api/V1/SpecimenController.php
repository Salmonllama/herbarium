<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PlanLimitsContract;
use App\Domain\Specimens\CatalogNumberGenerator;
use App\Domain\Specimens\DeterminationService;
use App\Enums\CurationStatus;
use App\Enums\GeoprivacyLevel;
use App\Enums\SpecimenVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\SpecimenResource;
use App\Models\Herbarium;
use App\Models\Specimen;
use App\Models\Taxon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SpecimenController extends Controller
{
    public function index(Request $request, Herbarium $herbarium): JsonResponse
    {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Herbarium not found or private.'], 403);
        }

        $query = $this->buildFilteredQuery($request, $herbarium, $user);

        $relations = ['herbarium', 'currentDetermination.taxon'];
        if (class_exists(\App\Models\Media::class)) {
            $relations[] = 'media';
        }
        $query->with($relations);

        $sort = $request->string('sort', 'created_desc')->toString();
        match ($sort) {
            'catalog_asc' => $query->orderBy('specimens.catalog_number', 'asc'),
            'catalog_desc' => $query->orderBy('specimens.catalog_number', 'desc'),
            'event_date_desc' => $query->orderByDesc('specimens.event_date')->orderByDesc('specimens.created_at'),
            'event_date_asc' => $query->orderBy('specimens.event_date', 'asc'),
            default => $query->orderByDesc('specimens.created_at'),
        };

        $specimens = $query->paginate(min(100, max(1, (int) $request->input('per_page', 24))));

        return SpecimenResource::collection($specimens)->response();
    }

    public function facets(Request $request, Herbarium $herbarium): JsonResponse
    {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Herbarium not found or private.'], 403);
        }

        $baseIds = Specimen::query()->visibleTo($herbarium, $user)->select('specimens.id');

        $families = DB::table('specimens')
            ->join('determinations', 'specimens.current_determination_id', '=', 'determinations.id')
            ->join('taxa', 'determinations.taxon_id', '=', 'taxa.id')
            ->whereIn('specimens.id', $baseIds)
            ->whereNotNull('taxa.family')
            ->groupBy('taxa.family')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(50)
            ->select('taxa.family as value', DB::raw('COUNT(*) as count'))
            ->get();

        $genera = DB::table('specimens')
            ->join('determinations', 'specimens.current_determination_id', '=', 'determinations.id')
            ->join('taxa', 'determinations.taxon_id', '=', 'taxa.id')
            ->whereIn('specimens.id', $baseIds)
            ->whereNotNull('taxa.genus')
            ->groupBy('taxa.genus')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(50)
            ->select('taxa.genus as value', DB::raw('COUNT(*) as count'))
            ->get();

        $countries = DB::table('specimens')
            ->whereIn('id', $baseIds)
            ->whereNotNull('country')
            ->groupBy('country')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(50)
            ->select('country as value', DB::raw('COUNT(*) as count'))
            ->get();

        $preparations = DB::table('specimens')
            ->whereIn('id', $baseIds)
            ->whereNotNull('preparations')
            ->groupBy('preparations')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('preparations as value', DB::raw('COUNT(*) as count'))
            ->get();

        $typeStatuses = DB::table('specimens')
            ->whereIn('id', $baseIds)
            ->whereNotNull('type_status')
            ->groupBy('type_status')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->select('type_status as value', DB::raw('COUNT(*) as count'))
            ->get();

        return response()->json([
            'families' => $families,
            'genera' => $genera,
            'countries' => $countries,
            'preparations' => $preparations,
            'type_statuses' => $typeStatuses,
        ]);
    }

    public function store(
        Request $request,
        Herbarium $herbarium,
        CatalogNumberGenerator $generator,
        DeterminationService $determinations,
        PlanLimitsContract $planLimits,
    ): JsonResponse {
        Gate::authorize('create', [Specimen::class, $herbarium]);

        if (! $planLimits->canCreateSpecimen($herbarium)) {
            return response()->json(['message' => 'Specimen quota reached for this herbarium plan.'], 403);
        }

        $validated = $this->validateSpecimen($request, $herbarium);
        $user = $request->user();
        $canCurate = $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canCurate();

        $specimen = DB::transaction(function () use ($validated, $herbarium, $user, $canCurate, $generator, $determinations) {
            if (! empty($validated['catalog_number'])) {
                $generator->assertUnique($herbarium, $validated['catalog_number']);
                $catalogNumber = $validated['catalog_number'];
            } else {
                $catalogNumber = $generator->nextFor($herbarium);
            }

            $visibility = $canCurate
                ? ($validated['visibility'] ?? SpecimenVisibility::Draft->value)
                : SpecimenVisibility::Draft->value;

            $specimenData = collect($validated)
                ->except(['taxon_id', 'identified_by', 'date_identified', 'identification_qualifier', 'identification_remarks'])
                ->all();

            $specimen = Specimen::create(array_merge($specimenData, [
                'herbarium_id' => $herbarium->id,
                'catalog_number' => $catalogNumber,
                'visibility' => $visibility,
                'curation_status' => $validated['curation_status'] ?? CurationStatus::Unmounted->value,
                'geoprivacy' => $validated['geoprivacy'] ?? GeoprivacyLevel::Inherit->value,
                'preparations' => $validated['preparations'] ?? 'pressed sheet',
                'geodetic_datum' => $validated['geodetic_datum'] ?? 'WGS84',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));

            if (array_key_exists('taxon_id', $validated) && $validated['taxon_id'] !== null) {
                $determinations->record($specimen, [
                    'taxon_id' => $validated['taxon_id'],
                    'identified_by' => $validated['identified_by'] ?? $user->name,
                    'date_identified' => $validated['date_identified'] ?? now()->toDateString(),
                    'identification_qualifier' => $validated['identification_qualifier'] ?? null,
                    'identification_remarks' => $validated['identification_remarks'] ?? null,
                ], $user);
            }

            return $specimen;
        });

        $specimen->load(['herbarium', 'currentDetermination.taxon', 'determinations.taxon']);

        return (new SpecimenResource($specimen))->response()->setStatusCode(201);
    }

    public function show(Request $request, Herbarium $herbarium, string $catalogNumber): JsonResponse
    {
        $specimen = $this->findSpecimen($herbarium, $catalogNumber);
        $user = $request->user('sanctum');

        if (! Gate::forUser($user)->allows('view', $specimen)) {
            return response()->json(['message' => 'Specimen not found or private.'], 403);
        }

        $relations = ['herbarium', 'currentDetermination.taxon', 'determinations.taxon'];
        if (class_exists(\App\Models\Media::class)) {
            $relations[] = 'media';
        }
        $specimen->load($relations);

        return (new SpecimenResource($specimen))->response();
    }

    public function update(
        Request $request,
        Herbarium $herbarium,
        string $catalogNumber,
        CatalogNumberGenerator $generator,
        DeterminationService $determinations,
    ): JsonResponse {
        $specimen = $this->findSpecimen($herbarium, $catalogNumber);
        Gate::authorize('update', $specimen);

        $validated = $this->validateSpecimen($request, $herbarium, $specimen);
        $user = $request->user();
        $canCurate = $user->is_instance_admin || (bool) $user->roleIn($herbarium)?->canCurate();

        if (! $canCurate) {
            unset($validated['visibility']);
        }

        if (isset($validated['catalog_number']) && $validated['catalog_number'] !== $specimen->catalog_number) {
            $generator->assertUnique($herbarium, $validated['catalog_number'], $specimen->id);
        }

        DB::transaction(function () use ($specimen, $validated, $user, $determinations) {
            $specimenData = collect($validated)
                ->except(['taxon_id', 'identified_by', 'date_identified', 'identification_qualifier', 'identification_remarks'])
                ->all();
            $specimenData['updated_by'] = $user->id;

            $specimen->update($specimenData);

            if (array_key_exists('taxon_id', $validated)) {
                $currentTaxonId = $specimen->currentDetermination?->taxon_id;
                if ($validated['taxon_id'] !== $currentTaxonId) {
                    $determinations->record($specimen, [
                        'taxon_id' => $validated['taxon_id'],
                        'identified_by' => $validated['identified_by'] ?? $user->name,
                        'date_identified' => $validated['date_identified'] ?? now()->toDateString(),
                        'identification_qualifier' => $validated['identification_qualifier'] ?? null,
                        'identification_remarks' => $validated['identification_remarks'] ?? null,
                    ], $user);
                }
            }
        });

        $relations = ['herbarium', 'currentDetermination.taxon', 'determinations.taxon'];
        if (class_exists(\App\Models\Media::class)) {
            $relations[] = 'media';
        }

        return (new SpecimenResource($specimen->fresh($relations)))->response();
    }

    public function publish(Request $request, Herbarium $herbarium, string $catalogNumber): JsonResponse
    {
        $specimen = $this->findSpecimen($herbarium, $catalogNumber);
        Gate::authorize('publish', $specimen);

        $specimen->update([
            'visibility' => SpecimenVisibility::Published,
            'updated_by' => $request->user()->id,
        ]);

        return (new SpecimenResource($specimen->fresh(['herbarium', 'currentDetermination.taxon'])))->response();
    }

    public function unpublish(Request $request, Herbarium $herbarium, string $catalogNumber): JsonResponse
    {
        $specimen = $this->findSpecimen($herbarium, $catalogNumber);
        Gate::authorize('publish', $specimen);

        $specimen->update([
            'visibility' => $request->input('visibility') === 'private'
                ? SpecimenVisibility::Private
                : SpecimenVisibility::Draft,
            'updated_by' => $request->user()->id,
        ]);

        return (new SpecimenResource($specimen->fresh(['herbarium', 'currentDetermination.taxon'])))->response();
    }

    public function destroy(Herbarium $herbarium, string $catalogNumber): JsonResponse
    {
        $specimen = $this->findSpecimen($herbarium, $catalogNumber);
        Gate::authorize('delete', $specimen);

        $specimen->delete();

        return response()->json(['message' => 'Specimen archived.']);
    }

    public function buildFilteredQuery(Request $request, Herbarium $herbarium, ?\App\Models\User $user): Builder
    {
        $query = Specimen::query()->visibleTo($herbarium, $user);

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('specimens.catalog_number', 'ilike', "%{$search}%")
                    ->orWhere('specimens.field_number', 'ilike', "%{$search}%")
                    ->orWhere('specimens.recorded_by', 'ilike', "%{$search}%")
                    ->orWhere('specimens.country', 'ilike', "%{$search}%")
                    ->orWhere('specimens.state_province', 'ilike', "%{$search}%")
                    ->orWhere('specimens.county', 'ilike', "%{$search}%")
                    ->orWhereHas('currentDetermination.taxon', function (Builder $tq) use ($search) {
                        $tq->where('scientific_name', 'ilike', "%{$search}%")
                            ->orWhere('family', 'ilike', "%{$search}%")
                            ->orWhere('genus', 'ilike', "%{$search}%")
                            ->orWhereRaw('common_names::text ILIKE ?', ["%{$search}%"]);
                    });
            });
        }

        if ($family = $request->string('family')->trim()->toString()) {
            $query->whereHas('currentDetermination.taxon', fn (Builder $tq) => $tq->where('family', $family));
        }

        if ($genus = $request->string('genus')->trim()->toString()) {
            $query->whereHas('currentDetermination.taxon', fn (Builder $tq) => $tq->where('genus', $genus));
        }

        if ($taxonId = $request->string('taxon_id')->trim()->toString()) {
            $query->whereHas('currentDetermination', fn (Builder $dq) => $dq->where('taxon_id', $taxonId));
        }

        if ($collector = $request->string('recorded_by')->trim()->toString()) {
            $query->where('specimens.recorded_by', 'ilike', "%{$collector}%");
        }

        if ($country = $request->string('country')->trim()->toString()) {
            $query->where('specimens.country', $country);
        }

        if ($state = $request->string('state_province')->trim()->toString()) {
            $query->where('specimens.state_province', $state);
        }

        if ($yearFrom = $request->integer('year_from')) {
            $query->whereYear('specimens.event_date', '>=', $yearFrom);
        }

        if ($yearTo = $request->integer('year_to')) {
            $query->whereYear('specimens.event_date', '<=', $yearTo);
        }

        if ($typeStatus = $request->string('type_status')->trim()->toString()) {
            $query->where('specimens.type_status', $typeStatus);
        }

        if ($preparations = $request->string('preparations')->trim()->toString()) {
            $query->where('specimens.preparations', $preparations);
        }

        if ($visibility = $request->string('visibility')->trim()->toString()) {
            $query->where('specimens.visibility', $visibility);
        }

        if ($status = $request->string('curation_status')->trim()->toString()) {
            $query->where('specimens.curation_status', $status);
        }

        if ($request->boolean('has_media') && class_exists(\App\Models\Media::class)) {
            $query->whereHas('media');
        }

        if ($bbox = $request->string('bbox')->trim()->toString()) {
            $parts = array_map('floatval', explode(',', $bbox));
            if (count($parts) === 4) {
                [$minLng, $minLat, $maxLng, $maxLat] = $parts;
                $canViewExact = $user ? $user->canViewExactCoordinatesIn($herbarium) : false;
                $query->withinBoundingBox($minLng, $minLat, $maxLng, $maxLat, $herbarium, $canViewExact);
            }
        }

        return $query;
    }

    private function findSpecimen(Herbarium $herbarium, string $catalogNumber): Specimen
    {
        return Specimen::query()
            ->where('herbarium_id', $herbarium->id)
            ->where('catalog_number', rawurldecode($catalogNumber))
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSpecimen(Request $request, Herbarium $herbarium, ?Specimen $existing = null): array
    {
        $validated = $request->validate([
            'catalog_number' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'field_number' => ['nullable', 'string', 'max:100'],
            'visibility' => ['sometimes', Rule::enum(SpecimenVisibility::class)],
            'curation_status' => ['sometimes', Rule::enum(CurationStatus::class)],

            // Determination shortcut
            'taxon_id' => ['nullable', 'uuid'],
            'identified_by' => ['nullable', 'string', 'max:255'],
            'date_identified' => ['nullable', 'date'],
            'identification_qualifier' => ['nullable', 'string', 'max:32'],
            'identification_remarks' => ['nullable', 'string', 'max:2000'],

            // Darwin Core fields
            'recorded_by' => ['nullable', 'string', 'max:255'],
            'recorded_by_ids' => ['nullable', 'array'],
            'recorded_by_ids.*' => ['string', 'max:100'],
            'event_date' => ['nullable', 'date'],
            'event_date_end' => ['nullable', 'date'],
            'verbatim_event_date' => ['nullable', 'string', 'max:120'],
            'habitat' => ['nullable', 'string', 'max:5000'],
            'substrate' => ['nullable', 'string', 'max:255'],
            'associated_taxa' => ['nullable', 'string', 'max:2000'],
            'reproductive_condition' => ['nullable', 'string', 'max:100'],
            'preparations' => ['nullable', 'string', 'max:120'],
            'type_status' => ['nullable', 'string', 'max:64'],
            'occurrence_remarks' => ['nullable', 'string', 'max:5000'],

            // Physical storage
            'storage_room' => ['nullable', 'string', 'max:80'],
            'storage_cabinet' => ['nullable', 'string', 'max:80'],
            'storage_shelf' => ['nullable', 'string', 'max:80'],
            'storage_folder' => ['nullable', 'string', 'max:120'],
            'storage_notes' => ['nullable', 'string', 'max:2000'],

            // Location
            'country_code' => ['nullable', 'string', 'size:2'],
            'country' => ['nullable', 'string', 'max:120'],
            'state_province' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'municipality' => ['nullable', 'string', 'max:120'],
            'locality' => ['nullable', 'string', 'max:3000'],
            'elevation_min_m' => ['nullable', 'integer', 'min:-500', 'max:9000'],
            'elevation_max_m' => ['nullable', 'integer', 'min:-500', 'max:9000'],
            'decimal_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'decimal_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'coordinate_uncertainty_m' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'geodetic_datum' => ['nullable', 'string', 'max:32'],
            'geoprivacy' => ['sometimes', Rule::enum(GeoprivacyLevel::class)],
        ]);

        if (! empty($validated['taxon_id'])) {
            $taxonExists = Taxon::query()
                ->where('herbarium_id', $herbarium->id)
                ->whereKey($validated['taxon_id'])
                ->exists();
            if (! $taxonExists) {
                abort(response()->json([
                    'message' => 'The selected taxon does not belong to this herbarium.',
                    'errors' => ['taxon_id' => ['Selected taxon not found in this herbarium.']],
                ], 422));
            }
        }

        return $validated;
    }
}
