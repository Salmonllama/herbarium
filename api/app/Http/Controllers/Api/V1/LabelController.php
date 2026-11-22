<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Labels\LabelPdfRenderer;
use App\Http\Controllers\Controller;
use App\Models\Herbarium;
use App\Models\Specimen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LabelController extends Controller
{
    public function pdf(Request $request, Herbarium $herbarium, LabelPdfRenderer $renderer): Response|JsonResponse
    {
        Gate::authorize('contribute', $herbarium);

        $validated = $request->validate([
            'catalog_numbers' => ['required', 'array', 'min:1', 'max:200'],
            'catalog_numbers.*' => ['string', 'max:100'],
            'template' => ['sometimes', 'string', 'in:letter_4up,a4_4up,single_4x3'],
            'include_exact_coordinates' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $specimens = Specimen::query()
            ->visibleTo($herbarium, $user)
            ->whereIn('catalog_number', $validated['catalog_numbers'])
            ->with(['herbarium', 'currentDetermination.taxon'])
            ->orderBy('catalog_number')
            ->get();

        if ($specimens->isEmpty()) {
            return response()->json(['message' => 'No matching accessible specimens found.'], 404);
        }

        $allowExact = (bool) ($validated['include_exact_coordinates'] ?? false)
            && $user->canViewExactCoordinatesIn($herbarium);

        $template = $validated['template'] ?? 'letter_4up';
        $pdfBytes = $renderer->render($herbarium, $specimens, $template, $allowExact);

        $filename = "{$herbarium->slug}-labels-".now()->format('Ymd-His').'.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
