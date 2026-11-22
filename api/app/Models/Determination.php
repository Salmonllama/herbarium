<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Determination extends Model
{
    use HasUuids;

    protected $fillable = [
        'specimen_id',
        'taxon_id',
        'identified_by',
        'identified_by_user_id',
        'date_identified',
        'identification_qualifier',
        'identification_remarks',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'date_identified' => 'date:Y-m-d',
            'is_current' => 'boolean',
        ];
    }

    public function specimen(): BelongsTo
    {
        return $this->belongsTo(Specimen::class);
    }

    public function taxon(): BelongsTo
    {
        return $this->belongsTo(Taxon::class);
    }

    public function identifierUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identified_by_user_id');
    }
}
