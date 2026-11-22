<?php

namespace App\Models;

use App\Enums\HerbariumRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'orcid_id',
        'institution',
        'is_instance_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_instance_admin' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HerbariumMembership::class);
    }

    public function herbaria(): BelongsToMany
    {
        return $this->belongsToMany(Herbarium::class, 'herbarium_memberships')
            ->withPivot(['id', 'role', 'can_view_exact_coordinates'])
            ->withTimestamps();
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OAuthIdentity::class);
    }

    public function membershipFor(Herbarium $herbarium): ?HerbariumMembership
    {
        if ($this->relationLoaded('memberships')) {
            return $this->memberships->firstWhere('herbarium_id', $herbarium->id);
        }

        return $this->memberships()->where('herbarium_id', $herbarium->id)->first();
    }

    public function roleIn(Herbarium $herbarium): ?HerbariumRole
    {
        $membership = $this->membershipFor($herbarium);

        return $membership?->role;
    }

    public function canViewExactCoordinatesIn(Herbarium $herbarium): bool
    {
        if ($this->is_instance_admin) {
            return true;
        }

        $membership = $this->membershipFor($herbarium);
        if (! $membership) {
            return false;
        }

        return $membership->role->canCurate() || $membership->can_view_exact_coordinates;
    }
}
