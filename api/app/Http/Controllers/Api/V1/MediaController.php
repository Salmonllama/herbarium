<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PlanLimitsContract;
use App\Domain\Geoprivacy\GeoprivacyService;
use App\Domain\Media\ExifExtractor;
use App\Enums\GeoprivacyLevel;
use App\Enums\MediaCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\MediaResource;
use App\Jobs\ProcessMediaDerivatives;
use App\Models\Herbarium;
use App\Models\Media;
use App\Models\Specimen;
use App\Models\Taxon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function storeForSpecimen(
        Request $request,
        Herbarium $herbarium,
        string $catalogNumber,
        ExifExtractor $exifExtractor,
        PlanLimitsContract $planLimits,
    ): JsonResponse {
        $specimen = Specimen::query()
            ->where('herbarium_id', $herbarium->id)
            ->where('catalog_number', rawurldecode($catalogNumber))
            ->firstOrFail();

        Gate::authorize('update', $specimen);

        return $this->handleUpload($request, $herbarium, $specimen, $exifExtractor, $planLimits, MediaCategory::SheetScan);
    }

    public function storeForTaxon(
        Request $request,
        Herbarium $herbarium,
        Taxon $taxon,
        ExifExtractor $exifExtractor,
        PlanLimitsContract $planLimits,
    ): JsonResponse {
        abort_unless($taxon->herbarium_id === $herbarium->id, 404);
        Gate::authorize('update', $taxon);

        return $this->handleUpload($request, $herbarium, $taxon, $exifExtractor, $planLimits, MediaCategory::Reference);
    }

    public function update(Request $request, Herbarium $herbarium, Media $media): JsonResponse
    {
        abort_unless($media->herbarium_id === $herbarium->id, 404);
        $this->authorizeMediaWrite($request, $media);

        $validated = $request->validate([
            'category' => ['sometimes', Rule::enum(MediaCategory::class)],
            'caption' => ['nullable', 'string', 'max:500'],
            'photographer' => ['nullable', 'string', 'max:255'],
            'license_code' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);

        $media->update($validated);

        return (new MediaResource($media->fresh()))->response();
    }

    public function destroy(Request $request, Herbarium $herbarium, Media $media): JsonResponse
    {
        abort_unless($media->herbarium_id === $herbarium->id, 404);
        $this->authorizeMediaWrite($request, $media);

        $disk = Storage::disk($media->disk);
        foreach ([$media->original_path, $media->public_webp_path, $media->thumb_webp_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }

        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }

    public function serve(Request $request, Media $media, string $variant): StreamedResponse|JsonResponse
    {
        if (! in_array($variant, ['thumb', 'public', 'original'], true)) {
            return response()->json(['message' => 'Invalid media variant.'], 404);
        }

        $user = $request->user('sanctum');
        $mediable = $media->mediable;

        if ($mediable instanceof Specimen) {
            if (! Gate::forUser($user)->allows('view', $mediable)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            // Original files may contain raw EXIF GPS tags; protect them whenever coordinates are obscured/hidden
            if ($variant === 'original') {
                $herbarium = $media->herbarium;
                $effectiveGeo = app(GeoprivacyService::class)->effectiveLevel($mediable, $herbarium);
                $canViewExact = $user && (
                    $user->canViewExactCoordinatesIn($herbarium)
                    || $mediable->created_by === $user->id
                );

                if ($effectiveGeo !== GeoprivacyLevel::Exact && ! $canViewExact) {
                    return response()->json(['message' => 'Original file restricted due to specimen geoprivacy.'], 403);
                }
            }
        } elseif ($mediable instanceof Taxon) {
            if (! Gate::forUser($user)->allows('view', $mediable)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        } else {
            return response()->json(['message' => 'Media target not found.'], 404);
        }

        $path = match ($variant) {
            'thumb' => $media->thumb_webp_path ?: $media->public_webp_path,
            'original' => $media->original_path,
            default => $media->public_webp_path,
        };

        $disk = Storage::disk($media->disk);
        if (! $path || ! $disk->exists($path)) {
            return response()->json(['message' => 'Media derivative not ready yet.'], 404);
        }

        $mime = $variant === 'original' ? $media->mime_type : 'image/webp';

        return $disk->response($path, null, [
            'Content-Type' => $mime,
            'Cache-Control' => $variant === 'original' ? 'private, max-age=3600' : 'public, max-age=604800',
        ]);
    }

    private function handleUpload(
        Request $request,
        Herbarium $herbarium,
        Specimen|Taxon $mediable,
        ExifExtractor $exifExtractor,
        PlanLimitsContract $planLimits,
        MediaCategory $defaultCategory,
    ): JsonResponse {
        $maxKb = (int) config('herbarium.media.max_upload_kb', 51200);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', "max:{$maxKb}"],
            'category' => ['sometimes', Rule::enum(MediaCategory::class)],
            'caption' => ['nullable', 'string', 'max:500'],
            'photographer' => ['nullable', 'string', 'max:255'],
            'license_code' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);

        $file = $request->file('file');
        $sizeBytes = (int) $file->getSize();

        if (! $planLimits->canUploadMedia($herbarium, $sizeBytes)) {
            return response()->json(['message' => 'Storage quota reached for this herbarium plan.'], 403);
        }

        $exifHints = $exifExtractor->extract($file->getRealPath());

        $diskName = (string) config('herbarium.media.disk', 'local');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $mediaId = (string) Str::uuid();
        $originalPath = "herbaria/{$herbarium->id}/originals/{$mediaId}.{$ext}";

        Storage::disk($diskName)->putFileAs(
            "herbaria/{$herbarium->id}/originals",
            $file,
            "{$mediaId}.{$ext}",
        );

        $nextOrder = (int) ($validated['sort_order'] ?? ($mediable->media()->max('sort_order') + 1));

        $media = new Media([
            'herbarium_id' => $herbarium->id,
            'mediable_type' => $mediable::class,
            'mediable_id' => $mediable->id,
            'category' => $validated['category'] ?? $defaultCategory->value,
            'disk' => $diskName,
            'original_path' => $originalPath,
            'mime_type' => $file->getMimeType() ?: 'image/jpeg',
            'size_bytes' => $sizeBytes,
            'exif_extracted' => $exifHints,
            'caption' => $validated['caption'] ?? null,
            'photographer' => $validated['photographer'] ?? $request->user()->name,
            'license_code' => $validated['license_code'] ?? $herbarium->default_license,
            'sort_order' => $nextOrder,
            'processing_status' => 'pending',
            'created_by' => $request->user()->id,
        ]);
        $media->id = $mediaId;
        $media->save();

        ProcessMediaDerivatives::dispatch($media->id);

        return response()->json([
            'data' => new MediaResource($media->fresh()),
            'exif_hints' => [
                'captured_at' => $exifHints['captured_at'],
                'latitude' => $exifHints['latitude'],
                'longitude' => $exifHints['longitude'],
                'camera' => $exifHints['camera'],
            ],
        ], 201);
    }

    private function authorizeMediaWrite(Request $request, Media $media): void
    {
        $mediable = $media->mediable;
        if ($mediable instanceof Specimen) {
            Gate::authorize('update', $mediable);
        } elseif ($mediable instanceof Taxon) {
            Gate::authorize('update', $mediable);
        } else {
            abort(404);
        }
    }
}
