<?php

namespace App\Domain\Media;

use App\Contracts\MediaUrlResolver;
use App\Models\Media;

class DefaultMediaUrlResolver implements MediaUrlResolver
{
    public function url(Media $media, string $variant = 'public'): ?string
    {
        $path = match ($variant) {
            'thumb' => $media->thumb_webp_path ?: $media->public_webp_path,
            'original' => $media->original_path,
            default => $media->public_webp_path,
        };

        if (! $path) {
            return null;
        }

        return "/api/v1/media/{$media->id}/file/{$variant}";
    }
}
