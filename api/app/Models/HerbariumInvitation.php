<?php

namespace App\Models;

use App\Enums\HerbariumRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HerbariumInvitation extends Model
{
    use HasUuids;

    protected $fillable = [
        'herbarium_id',
        'email',
        'role',
        'can_view_exact_coordinates',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'role' => HerbariumRole::class,
            'can_view_exact_coordinates' => 'boolean',
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function herbarium(): BelongsTo
    {
        return $this->belongsTo(Herbarium::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}
