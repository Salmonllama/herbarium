<?php

namespace App\Models;

use App\Enums\MediaCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $attributes = [
        'category' => 'sheet_scan',
        'disk' => 'local',
        'sort_order' => 0,
        'processing_status' => 'pending',
    ];

    protected $fillable = [
        'herbarium_id',
        'mediable_type',
        'mediable_id',
        'category',
        'disk',
        'original_path',
        'public_webp_path',
        'thumb_webp_path',
        'width',
        'height',
        'mime_type',
        'size_bytes',
        'exif_extracted',
        'caption',
        'photographer',
        'license_code',
        'sort_order',
        'processing_status',
        'processing_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => MediaCategory::class,
            'exif_extracted' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function herbarium(): BelongsTo
    {
        return $this->belongsTo(Herbarium::class);
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
