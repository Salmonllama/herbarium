<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Taxonomy\TaxonLookupManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxonLookupController extends Controller
{
    public function __invoke(Request $request, TaxonLookupManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'source' => ['sometimes', 'string', 'in:gbif,powo'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ]);

        $source = $validated['source'] ?? 'gbif';
        $results = $manager->search($validated['q'], $source, (int) ($validated['limit'] ?? 10));

        return response()->json([
            'source' => $source,
            'query' => $validated['q'],
            'data' => $results,
        ]);
    }
}
