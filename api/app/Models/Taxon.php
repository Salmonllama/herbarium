<?php

namespace App\Models;

use App\Enums\TaxonRank;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Taxon extends Model
{
    use HasUuids, Searchable, SoftDeletes;

    protected $table = 'taxa';

    protected $attributes = [
        'taxon_rank' => 'species',
        'external_source' => 'manual',
        'is_sensitive_location' => false,
    ];

    protected $fillable = [
        'herbarium_id',
        'scientific_name',
        'scientific_name_authorship',
        'taxon_rank',
        'kingdom',
        'phylum',
        'taxon_class',
        'taxon_order',
        'family',
        'genus',
        'specific_epithet',
        'infraspecific_rank',
        'infraspecific_epithet',
        'common_names',
        'description_markdown',
        'morphology_notes',
        'ecology_notes',
        'conservation_status',
        'external_source',
        'external_taxon_id',
        'external_synced_at',
        'is_sensitive_location',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'taxon_rank' => TaxonRank::class,
            'common_names' => 'array',
            'external_synced_at' => 'datetime',
            'is_sensitive_location' => 'boolean',
        ];
    }

    public function herbarium(): BelongsTo
    {
        return $this->belongsTo(Herbarium::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function determinations(): HasMany
    {
        return $this->hasMany(Determination::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'scientific_name' => $this->scientific_name,
            'family' => $this->family,
            'genus' => $this->genus,
            'common_names' => collect($this->common_names ?? [])->pluck('name')->implode(' '),
        ];
    }
}
