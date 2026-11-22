<?php

namespace App\Http\Resources;

use App\Contracts\MediaUrlResolver;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resolver = app(MediaUrlResolver::class);

        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'category' => $this->category->value,
            'width' => $this->width,
            'height' => $this->height,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'caption' => $this->caption,
            'photographer' => $this->photographer,
            'license_code' => $this->license_code,
            'sort_order' => $this->sort_order,
            'processing_status' => $this->processing_status,
            'thumb_url' => $resolver->url($this->resource, 'thumb'),
            'public_url' => $resolver->url($this->resource, 'public'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
