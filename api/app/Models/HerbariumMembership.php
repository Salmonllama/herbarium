<?php

namespace App\Models;

use App\Enums\HerbariumRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HerbariumMembership extends Model
{
    use HasUuids;

    protected $fillable = [
        'herbarium_id',
        'user_id',
        'role',
        'can_view_exact_coordinates',
    ];

    protected function casts(): array
    {
        return [
            'role' => HerbariumRole::class,
            'can_view_exact_coordinates' => 'boolean',
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
