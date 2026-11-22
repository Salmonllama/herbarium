<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportExportJob extends Model
{
    use HasUuids;

    protected $table = 'import_export_jobs';

    protected $fillable = [
        'herbarium_id',
        'user_id',
        'type',
        'status',
        'disk',
        'file_path',
        'download_filename',
        'options',
        'summary',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'summary' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function herbarium(): BelongsTo
    {
        return $this->belongsTo(Herbarium::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
