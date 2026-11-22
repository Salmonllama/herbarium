<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\ImportExport\CsvExportService;
use App\Domain\ImportExport\CsvImportService;
use App\Domain\ImportExport\DwcaExportService;
use App\Http\Controllers\Controller;
use App\Models\Herbarium;
use App\Models\ImportExportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function previewCsv(Request $request, Herbarium $herbarium, CsvImportService $importer): JsonResponse
    {
        Gate::authorize('curate', $herbarium);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = file_get_contents($request->file('file')->getRealPath()) ?: '';

        return response()->json($importer->preview($content));
    }

    public function importCsv(Request $request, Herbarium $herbarium, CsvImportService $importer): JsonResponse
    {
        Gate::authorize('curate', $herbarium);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
            'mapping' => ['required'],
            'dry_run' => ['sometimes', 'boolean'],
            'default_visibility' => ['sometimes', 'string', 'in:draft,private,published'],
        ]);

        $mapping = is_string($validated['mapping'])
            ? (json_decode($validated['mapping'], true) ?? [])
            : (is_array($validated['mapping']) ? $validated['mapping'] : []);

        if (empty($mapping)) {
            return response()->json(['message' => 'Column mapping is required.'], 422);
        }

        $content = file_get_contents($request->file('file')->getRealPath()) ?: '';
        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $defaultVisibility = $validated['default_visibility'] ?? 'draft';

        $summary = $importer->import(
            $herbarium,
            $request->user(),
            $content,
            $mapping,
            $dryRun,
            $defaultVisibility,
        );

        $job = null;
        if (! $dryRun) {
            $job = ImportExportJob::create([
                'herbarium_id' => $herbarium->id,
                'user_id' => $request->user()->id,
                'type' => 'csv_import',
                'status' => 'completed',
                'options' => ['mapping' => $mapping, 'default_visibility' => $defaultVisibility],
                'summary' => $summary,
                'completed_at' => now(),
            ]);
        }

        return response()->json([
            'job_id' => $job?->id,
            'summary' => $summary,
        ], $dryRun ? 200 : 201);
    }

    public function exportCsv(
        Request $request,
        Herbarium $herbarium,
        SpecimenController $specimenController,
        CsvExportService $exporter,
    ): Response|JsonResponse {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $canViewExact = $user ? $user->canViewExactCoordinatesIn($herbarium) : false;

        $specimens = $specimenController->buildFilteredQuery($request, $herbarium, $user)
            ->with(['herbarium', 'currentDetermination.taxon'])
            ->orderBy('catalog_number')
            ->get();

        $csv = $exporter->generate($herbarium, $specimens, $canViewExact);
        $filename = "{$herbarium->slug}-specimens-".now()->format('Ymd').'.csv';

        if ($user && $user->membershipFor($herbarium)) {
            ImportExportJob::create([
                'herbarium_id' => $herbarium->id,
                'user_id' => $user->id,
                'type' => 'csv_export',
                'status' => 'completed',
                'download_filename' => $filename,
                'summary' => ['exported_count' => $specimens->count()],
                'completed_at' => now(),
            ]);
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportDwca(
        Request $request,
        Herbarium $herbarium,
        SpecimenController $specimenController,
        DwcaExportService $exporter,
    ): Response|JsonResponse {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $canViewExact = $user ? ($user->canViewExactCoordinatesIn($herbarium) && $request->boolean('include_exact_coordinates')) : false;

        $specimens = $specimenController->buildFilteredQuery($request, $herbarium, $user)
            ->with(['herbarium', 'currentDetermination.taxon', 'media'])
            ->orderBy('catalog_number')
            ->get();

        $zipBytes = $exporter->buildArchiveBytes($herbarium, $specimens, $canViewExact);
        $filename = "{$herbarium->slug}-dwca-".now()->format('Ymd').'.zip';

        if ($user && $user->membershipFor($herbarium)) {
            $diskName = (string) config('herbarium.media.disk', 'local');
            $job = ImportExportJob::create([
                'herbarium_id' => $herbarium->id,
                'user_id' => $user->id,
                'type' => 'dwca_export',
                'status' => 'completed',
                'disk' => $diskName,
                'download_filename' => $filename,
                'summary' => ['exported_count' => $specimens->count()],
                'completed_at' => now(),
            ]);
            $path = "herbaria/{$herbarium->id}/exports/{$job->id}.zip";
            Storage::disk($diskName)->put($path, $zipBytes);
            $job->update(['file_path' => $path]);
        }

        return response($zipBytes, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function indexJobs(Herbarium $herbarium): JsonResponse
    {
        Gate::authorize('curate', $herbarium);

        $jobs = ImportExportJob::query()
            ->where('herbarium_id', $herbarium->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ImportExportJob $job) => [
                'id' => $job->id,
                'type' => $job->type,
                'status' => $job->status,
                'download_filename' => $job->download_filename,
                'has_download' => (bool) $job->file_path,
                'summary' => $job->summary,
                'error_message' => $job->error_message,
                'completed_at' => $job->completed_at?->toIso8601String(),
                'created_at' => $job->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $jobs]);
    }

    public function downloadJob(Herbarium $herbarium, ImportExportJob $job): StreamedResponse|JsonResponse
    {
        Gate::authorize('curate', $herbarium);
        abort_unless($job->herbarium_id === $herbarium->id, 404);

        if (! $job->file_path || ! Storage::disk($job->disk)->exists($job->file_path)) {
            return response()->json(['message' => 'Export file not available.'], 404);
        }

        return Storage::disk($job->disk)->download(
            $job->file_path,
            $job->download_filename ?: basename($job->file_path),
        );
    }
}
