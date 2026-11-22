<?php

namespace App\Models;

use App\Enums\GeoprivacyLevel;
use App\Enums\HerbariumVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Herbarium extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'herbaria';

    protected $fillable = [
        'slug',
        'name',
        'institution_code',
        'custom_domain',
        'description',
        'contact_email',
        'website_url',
        'visibility',
        'id_prefix',
        'id_separator',
        'id_padding',
        'id_next_sequence',
        'default_geoprivacy',
        'default_license',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => HerbariumVisibility::class,
            'default_geoprivacy' => GeoprivacyLevel::class,
            'id_padding' => 'integer',
            'id_next_sequence' => 'integer',
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(HerbariumMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'herbarium_memberships')
            ->withPivot(['id', 'role', 'can_view_exact_coordinates'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HerbariumInvitation::class);
    }

    public function previewNextCatalogNumber(): string
    {
        $seq = str_pad((string) max(1, (int) $this->id_next_sequence), max(1, (int) $this->id_padding), '0', STR_PAD_LEFT);

        return $this->id_prefix.$this->id_separator.$seq;
    }

    public function isPubliclyAccessible(): bool
    {
        return in_array($this->visibility, [HerbariumVisibility::Public, HerbariumVisibility::Unlisted], true);
    }
}
