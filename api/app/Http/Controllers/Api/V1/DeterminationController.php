<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Specimens\DeterminationService;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeterminationResource;
use App\Models\Herbarium;
use App\Models\Specimen;
use App\Models\Taxon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DeterminationController extends Controller
{
    public function index(Request $request, Herbarium $herbarium, string $catalogNumber): JsonResponse
    {
        $specimen = Specimen::query()
            ->where('herbarium_id', $herbarium->id)
            ->where('catalog_number', rawurldecode($catalogNumber))
            ->firstOrFail();

        if (! Gate::forUser($request->user('sanctum'))->allows('view', $specimen)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $determinations = $specimen->determinations()->with('taxon')->get();

        return DeterminationResource::collection($determinations)->response();
    }

    public function store(
        Request $request,
        Herbarium $herbarium,
        string $catalogNumber,
        DeterminationService $service,
    ): JsonResponse {
        $specimen = Specimen::query()
            ->where('herbarium_id', $herbarium->id)
            ->where('catalog_number', rawurldecode($catalogNumber))
            ->firstOrFail();

        Gate::authorize('determine', $specimen);

        $validated = $request->validate([
            'taxon_id' => ['nullable', 'uuid'],
            'identified_by' => ['nullable', 'string', 'max:255'],
            'date_identified' => ['nullable', 'date'],
            'identification_qualifier' => ['nullable', 'string', 'max:32'],
            'identification_remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! empty($validated['taxon_id'])) {
            $exists = Taxon::query()
                ->where('herbarium_id', $herbarium->id)
                ->whereKey($validated['taxon_id'])
                ->exists();
            if (! $exists) {
                return response()->json([
                    'message' => 'Selected taxon does not belong to this herbarium.',
                    'errors' => ['taxon_id' => ['Invalid taxon for this herbarium.']],
                ], 422);
            }
        }

        $determination = $service->record($specimen, $validated, $request->user());

        return (new DeterminationResource($determination))->response()->setStatusCode(201);
    }
}
