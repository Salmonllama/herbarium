<?php

namespace App\Domain\Media;

use App\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ImageDerivativeService
{
    /**
     * Generates EXIF-stripped WebP thumbnail and high-resolution viewing derivatives.
     * Re-encoding through GD guarantees all raw EXIF metadata (including GPS coordinates)
     * is completely stripped from public derivatives.
     */
    public function process(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        if (! $disk->exists($media->original_path)) {
            throw new RuntimeException("Original media file missing at [{$media->original_path}].");
        }

        $rawBytes = $disk->get($media->original_path);
        $source = @imagecreatefromstring($rawBytes);

        if (! $source instanceof GdImage) {
            throw new RuntimeException('Unable to decode image for derivative generation.');
        }

        try {
            $orientation = (int) ($media->exif_extracted['orientation'] ?? 1);
            $source = $this->applyOrientation($source, $orientation);

            $width = imagesx($source);
            $height = imagesy($source);

            $quality = (int) config('herbarium.media.webp_quality', 84);
            $publicMaxEdge = (int) config('herbarium.media.public_max_edge', 3000);
            $thumbWidth = (int) config('herbarium.media.thumb_width', 400);

            $baseDir = "herbaria/{$media->herbarium_id}/derivatives/{$media->id}";
            $publicPath = "{$baseDir}/public.webp";
            $thumbPath = "{$baseDir}/thumb.webp";

            // High-resolution viewing derivative (EXIF-stripped)
            $publicImg = $this->resizeToMaxEdge($source, $publicMaxEdge);
            $disk->put($publicPath, $this->encodeWebp($publicImg, $quality));
            if ($publicImg !== $source) {
                imagedestroy($publicImg);
            }

            // Thumbnail derivative (EXIF-stripped)
            $thumbImg = $this->resizeToWidth($source, $thumbWidth);
            $disk->put($thumbPath, $this->encodeWebp($thumbImg, $quality));
            if ($thumbImg !== $source) {
                imagedestroy($thumbImg);
            }

            $media->update([
                'width' => $width,
                'height' => $height,
                'public_webp_path' => $publicPath,
                'thumb_webp_path' => $thumbPath,
                'processing_status' => 'ready',
                'processing_error' => null,
            ]);
        } catch (Throwable $e) {
            $media->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            imagedestroy($source);
        }
    }

    private function applyOrientation(GdImage $img, int $orientation): GdImage
    {
        return match ($orientation) {
            3 => imagerotate($img, 180, 0) ?: $img,
            6 => imagerotate($img, -90, 0) ?: $img,
            8 => imagerotate($img, 90, 0) ?: $img,
            default => $img,
        };
    }

    private function resizeToMaxEdge(GdImage $source, int $maxEdge): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $longest = max($w, $h);

        if ($longest <= $maxEdge) {
            return $source;
        }

        $scale = $maxEdge / $longest;
        $newW = max(1, (int) round($w * $scale));
        $newH = max(1, (int) round($h * $scale));

        return $this->resample($source, $newW, $newH);
    }

    private function resizeToWidth(GdImage $source, int $targetWidth): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);

        if ($w <= $targetWidth) {
            return $source;
        }

        $scale = $targetWidth / $w;
        $newH = max(1, (int) round($h * $scale));

        return $this->resample($source, $targetWidth, $newH);
    }

    private function resample(GdImage $source, int $newW, int $newH): GdImage
    {
        $canvas = imagecreatetruecolor($newW, $newH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, imagesx($source), imagesy($source));

        return $canvas;
    }

    private function encodeWebp(GdImage $img, int $quality): string
    {
        imagepalettetotruecolor($img);
        ob_start();
        imagewebp($img, null, $quality);
        $bytes = ob_get_clean();

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Failed to encode WebP derivative.');
        }

        return $bytes;
    }
}
