<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SpecimenVisibility;
use App\Enums\TaxonRank;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaxonResource;
use App\Models\Herbarium;
use App\Models\Taxon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TaxonController extends Controller
{
    public function index(Request $request, Herbarium $herbarium): JsonResponse
    {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Herbarium not found or private.'], 403);
        }

        $query = $herbarium->taxa()->orderBy('scientific_name');

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('scientific_name', 'ilike', "%{$search}%")
                    ->orWhere('family', 'ilike', "%{$search}%")
                    ->orWhere('genus', 'ilike', "%{$search}%")
                    ->orWhereRaw("common_names::text ILIKE ?", ["%{$search}%"]);
            });
        }

        if ($family = $request->string('family')->trim()->toString()) {
            $query->where('family', $family);
        }

        if ($genus = $request->string('genus')->trim()->toString()) {
            $query->where('genus', $genus);
        }

        if ($request->has('is_sensitive_location')) {
            $query->where('is_sensitive_location', $request->boolean('is_sensitive_location'));
        }

        $canSeeDrafts = $user && ($user->is_instance_admin || $user->roleIn($herbarium)?->canCurate());
        $query->addSelect([
            'specimens_count' => DB::table('specimens')
                ->join('determinations', 'specimens.current_determination_id', '=', 'determinations.id')
                ->whereColumn('determinations.taxon_id', 'taxa.id')
                ->where('specimens.herbarium_id', $herbarium->id)
                ->whereNull('specimens.deleted_at')
                ->when(! $canSeeDrafts, fn ($q) => $q->where('specimens.visibility', SpecimenVisibility::Published->value))
                ->selectRaw('COUNT(*)'),
        ]);

        $taxa = $query->paginate(min(100, max(1, (int) $request->input('per_page', 30))));

        return TaxonResource::collection($taxa)->response();
    }

    public function tree(Request $request, Herbarium $herbarium): JsonResponse
    {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Herbarium not found or private.'], 403);
        }

        $canSeeDrafts = $user && ($user->is_instance_admin || $user->roleIn($herbarium)?->canCurate());

        $rows = DB::table('taxa')
            ->leftJoin('determinations', function ($join) {
                $join->on('determinations.taxon_id', '=', 'taxa.id')
                    ->where('determinations.is_current', true);
            })
            ->leftJoin('specimens', function ($join) use ($canSeeDrafts) {
                $join->on('specimens.current_determination_id', '=', 'determinations.id')
                    ->whereNull('specimens.deleted_at');
                if (! $canSeeDrafts) {
                    $join->where('specimens.visibility', SpecimenVisibility::Published->value);
                }
            })
            ->where('taxa.herbarium_id', $herbarium->id)
            ->whereNull('taxa.deleted_at')
            ->groupBy('taxa.id', 'taxa.family', 'taxa.genus', 'taxa.scientific_name', 'taxa.scientific_name_authorship', 'taxa.taxon_rank')
            ->orderByRaw("COALESCE(taxa.family, 'ZZZ_Unassigned') ASC, COALESCE(taxa.genus, 'ZZZ_Unassigned') ASC, taxa.scientific_name ASC")
            ->select([
                'taxa.id',
                'taxa.family',
                'taxa.genus',
                'taxa.scientific_name',
                'taxa.scientific_name_authorship',
                'taxa.taxon_rank',
                DB::raw('COUNT(specimens.id) as specimen_count'),
            ])
            ->get();

        $tree = [];
        foreach ($rows as $row) {
            $familyKey = $row->family ?: 'Unassigned Family';
            $genusKey = $row->genus ?: 'Unassigned Genus';

            if (! isset($tree[$familyKey])) {
                $tree[$familyKey] = [
                    'family' => $familyKey,
                    'specimen_count' => 0,
                    'taxa_count' => 0,
                    'genera' => [],
                ];
            }

            if (! isset($tree[$familyKey]['genera'][$genusKey])) {
                $tree[$familyKey]['genera'][$genusKey] = [
                    'genus' => $genusKey,
                    'specimen_count' => 0,
                    'taxa' => [],
                ];
            }

            $count = (int) $row->specimen_count;
            $tree[$familyKey]['specimen_count'] += $count;
            $tree[$familyKey]['taxa_count']++;
            $tree[$familyKey]['genera'][$genusKey]['specimen_count'] += $count;
            $tree[$familyKey]['genera'][$genusKey]['taxa'][] = [
                'id' => $row->id,
                'scientific_name' => $row->scientific_name,
                'scientific_name_authorship' => $row->scientific_name_authorship,
                'taxon_rank' => $row->taxon_rank,
                'specimen_count' => $count,
            ];
        }

        $formatted = array_values(array_map(function (array $fam) {
            $fam['genera'] = array_values($fam['genera']);

            return $fam;
        }, $tree));

        return response()->json(['data' => $formatted]);
    }

    public function store(Request $request, Herbarium $herbarium): JsonResponse
    {
        Gate::authorize('create', [Taxon::class, $herbarium]);

        $validated = $this->validateTaxon($request, $herbarium);
        $validated['herbarium_id'] = $herbarium->id;
        $validated['created_by'] = $request->user()->id;

        if (($validated['external_source'] ?? 'manual') !== 'manual') {
            $validated['external_synced_at'] = now();
        }

        $taxon = Taxon::create($validated);

        return (new TaxonResource($taxon))->response()->setStatusCode(201);
    }

    public function show(Request $request, Herbarium $herbarium, Taxon $taxon): JsonResponse
    {
        abort_unless($taxon->herbarium_id === $herbarium->id, 404);
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $taxon)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (class_exists(\App\Models\Media::class)) {
            $taxon->load('media');
        }

        return (new TaxonResource($taxon))->response();
    }

    public function update(Request $request, Herbarium $herbarium, Taxon $taxon): JsonResponse
    {
        abort_unless($taxon->herbarium_id === $herbarium->id, 404);
        Gate::authorize('update', $taxon);

        $validated = $this->validateTaxon($request, $herbarium, $taxon);
        $taxon->update($validated);

        return (new TaxonResource($taxon->fresh()))->response();
    }

    public function destroy(Herbarium $herbarium, Taxon $taxon): JsonResponse
    {
        abort_unless($taxon->herbarium_id === $herbarium->id, 404);
        Gate::authorize('delete', $taxon);

        $taxon->delete();

        return response()->json(['message' => 'Taxon archived.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTaxon(Request $request, Herbarium $herbarium, ?Taxon $existing = null): array
    {
        $isUpdate = $existing !== null;
        $reqRule = $isUpdate ? 'sometimes' : 'required';

        $validated = $request->validate([
            'scientific_name' => [$reqRule, 'string', 'max:255'],
            'scientific_name_authorship' => ['nullable', 'string', 'max:255'],
            'taxon_rank' => ['sometimes', Rule::enum(TaxonRank::class)],
            'kingdom' => ['nullable', 'string', 'max:100'],
            'phylum' => ['nullable', 'string', 'max:100'],
            'taxon_class' => ['nullable', 'string', 'max:100'],
            'taxon_order' => ['nullable', 'string', 'max:100'],
            'family' => ['nullable', 'string', 'max:100'],
            'genus' => ['nullable', 'string', 'max:100'],
            'specific_epithet' => ['nullable', 'string', 'max:100'],
            'infraspecific_rank' => ['nullable', 'string', 'max:32'],
            'infraspecific_epithet' => ['nullable', 'string', 'max:100'],
            'common_names' => ['nullable', 'array'],
            'common_names.*.name' => ['required_with:common_names', 'string', 'max:120'],
            'common_names.*.language' => ['nullable', 'string', 'max:16'],
            'description_markdown' => ['nullable', 'string', 'max:20000'],
            'morphology_notes' => ['nullable', 'string', 'max:10000'],
            'ecology_notes' => ['nullable', 'string', 'max:10000'],
            'conservation_status' => ['nullable', 'string', 'max:32'],
            'external_source' => ['sometimes', 'string', 'in:manual,gbif,powo'],
            'external_taxon_id' => ['nullable', 'string', 'max:128'],
            'is_sensitive_location' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['scientific_name'])) {
            $duplicate = $herbarium->taxa()
                ->whereRaw('LOWER(scientific_name) = ?', [strtolower(trim($validated['scientific_name']))])
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                ->exists();

            if ($duplicate) {
                abort(response()->json([
                    'message' => 'A taxon with this scientific name already exists in this herbarium.',
                    'errors' => ['scientific_name' => ['This scientific name already exists in this herbarium.']],
                ], 422));
            }
        }

        return $validated;
    }
}
