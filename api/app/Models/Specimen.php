<?php

namespace App\Models;

use App\Domain\Geoprivacy\GeoprivacyService;
use App\Enums\CurationStatus;
use App\Enums\GeoprivacyLevel;
use App\Enums\SpecimenVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Specimen extends Model
{
    use HasUuids, Searchable, SoftDeletes;

    protected $attributes = [
        'visibility' => 'draft',
        'curation_status' => 'unmounted',
        'geoprivacy' => 'inherit',
        'preparations' => 'pressed sheet',
        'geodetic_datum' => 'WGS84',
    ];

    protected $fillable = [
        'herbarium_id',
        'catalog_number',
        'field_number',
        'current_determination_id',
        'visibility',
        'curation_status',
        'recorded_by',
        'recorded_by_ids',
        'event_date',
        'event_date_end',
        'verbatim_event_date',
        'habitat',
        'substrate',
        'associated_taxa',
        'reproductive_condition',
        'preparations',
        'type_status',
        'occurrence_remarks',
        'storage_room',
        'storage_cabinet',
        'storage_shelf',
        'storage_folder',
        'storage_notes',
        'country_code',
        'country',
        'state_province',
        'county',
        'municipality',
        'locality',
        'elevation_min_m',
        'elevation_max_m',
        'decimal_latitude',
        'decimal_longitude',
        'coordinate_uncertainty_m',
        'geodetic_datum',
        'geoprivacy',
        'obscured_latitude',
        'obscured_longitude',
        'obscured_uncertainty_m',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => SpecimenVisibility::class,
            'curation_status' => CurationStatus::class,
            'geoprivacy' => GeoprivacyLevel::class,
            'recorded_by_ids' => 'array',
            'event_date' => 'date:Y-m-d',
            'event_date_end' => 'date:Y-m-d',
            'elevation_min_m' => 'integer',
            'elevation_max_m' => 'integer',
            'decimal_latitude' => 'float',
            'decimal_longitude' => 'float',
            'coordinate_uncertainty_m' => 'integer',
            'obscured_latitude' => 'float',
            'obscured_longitude' => 'float',
            'obscured_uncertainty_m' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Specimen $specimen) {
            if ($specimen->decimal_latitude !== null && $specimen->decimal_longitude !== null) {
                $id = $specimen->id ?: (string) $specimen->newUniqueId();
                if (! $specimen->id) {
                    $specimen->id = $id;
                }
                $obscured = app(GeoprivacyService::class)->computeObscuredCoordinates(
                    $id,
                    (float) $specimen->decimal_latitude,
                    (float) $specimen->decimal_longitude,
                    $specimen->coordinate_uncertainty_m,
                );
                $specimen->obscured_latitude = $obscured['latitude'];
                $specimen->obscured_longitude = $obscured['longitude'];
                $specimen->obscured_uncertainty_m = $obscured['uncertainty_m'];
            } else {
                $specimen->obscured_latitude = null;
                $specimen->obscured_longitude = null;
                $specimen->obscured_uncertainty_m = null;
            }
        });

        static::saved(function (Specimen $specimen) {
            if (DB::getDriverName() !== 'pgsql') {
                return;
            }

            if ($specimen->decimal_latitude !== null && $specimen->decimal_longitude !== null) {
                DB::update(
                    'UPDATE specimens SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, obscured_geom = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?',
                    [
                        (float) $specimen->decimal_longitude,
                        (float) $specimen->decimal_latitude,
                        (float) $specimen->obscured_longitude,
                        (float) $specimen->obscured_latitude,
                        $specimen->id,
                    ],
                );
            } else {
                DB::update('UPDATE specimens SET geom = NULL, obscured_geom = NULL WHERE id = ?', [$specimen->id]);
            }
        });
    }

    public function herbarium(): BelongsTo
    {
        return $this->belongsTo(Herbarium::class);
    }

    public function currentDetermination(): BelongsTo
    {
        return $this->belongsTo(Determination::class, 'current_determination_id');
    }

    public function determinations(): HasMany
    {
        return $this->hasMany(Determination::class)->orderByDesc('is_current')->orderByDesc('date_identified')->orderByDesc('created_at');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order')->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope specimens visible to the given user within a herbarium.
     */
    public function scopeVisibleTo(Builder $query, Herbarium $herbarium, ?User $user): Builder
    {
        $query->where('specimens.herbarium_id', $herbarium->id);

        if ($user && ($user->is_instance_admin || $user->roleIn($herbarium)?->canCurate())) {
            return $query;
        }

        if ($user && $user->roleIn($herbarium)?->canContribute()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('specimens.visibility', SpecimenVisibility::Published->value)
                    ->orWhere('specimens.created_by', $user->id);
            });
        }

        return $query->where('specimens.visibility', SpecimenVisibility::Published->value);
    }

    /**
     * Spatial bounding-box filter that enforces geoprivacy at the SQL layer
     * so unprivileged callers can NEVER probe exact coordinates of obscured/hidden records.
     */
    public function scopeWithinBoundingBox(
        Builder $query,
        float $minLng,
        float $minLat,
        float $maxLng,
        float $maxLat,
        Herbarium $herbarium,
        bool $canViewExact,
    ): Builder {
        $envelopeSql = 'ST_MakeEnvelope(?, ?, ?, ?, 4326)';

        if ($canViewExact) {
            return $query->whereNotNull('specimens.geom')
                ->whereRaw("ST_Intersects(specimens.geom::geometry, {$envelopeSql})", [$minLng, $minLat, $maxLng, $maxLat]);
        }

        // For unprivileged users:
        // - Effective Hidden specimens MUST NOT match any spatial query.
        // - Effective Obscured specimens match ONLY against obscured_geom.
        // - Effective Exact specimens match against geom.
        $defaultGeo = $herbarium->default_geoprivacy->value;

        return $query->where(function (Builder $spatial) use ($envelopeSql, $minLng, $minLat, $maxLng, $maxLat, $defaultGeo) {
            // Case 1: Explicitly exact OR (inherit + herbarium default exact + taxon not sensitive)
            $spatial->where(function (Builder $exactQ) use ($envelopeSql, $minLng, $minLat, $maxLng, $maxLat, $defaultGeo) {
                $exactQ->whereNotNull('specimens.geom')
                    ->whereRaw("ST_Intersects(specimens.geom::geometry, {$envelopeSql})", [$minLng, $minLat, $maxLng, $maxLat])
                    ->where(function (Builder $cond) use ($defaultGeo) {
                        $cond->where('specimens.geoprivacy', GeoprivacyLevel::Exact->value);
                        if ($defaultGeo === GeoprivacyLevel::Exact->value) {
                            $cond->orWhere(function (Builder $inh) {
                                $inh->where('specimens.geoprivacy', GeoprivacyLevel::Inherit->value)
                                    ->whereDoesntHave('currentDetermination.taxon', fn (Builder $t) => $t->where('is_sensitive_location', true));
                            });
                        }
                    });
            });

            // Case 2: Explicitly obscured OR (inherit + taxon sensitive) OR (inherit + herbarium default obscured)
            $spatial->orWhere(function (Builder $obsQ) use ($envelopeSql, $minLng, $minLat, $maxLng, $maxLat, $defaultGeo) {
                $obsQ->whereNotNull('specimens.obscured_geom')
                    ->whereRaw("ST_Intersects(specimens.obscured_geom::geometry, {$envelopeSql})", [$minLng, $minLat, $maxLng, $maxLat])
                    ->where(function (Builder $cond) use ($defaultGeo) {
                        $cond->where('specimens.geoprivacy', GeoprivacyLevel::Obscured->value)
                            ->orWhere(function (Builder $inhSens) {
                                $inhSens->where('specimens.geoprivacy', GeoprivacyLevel::Inherit->value)
                                    ->whereHas('currentDetermination.taxon', fn (Builder $t) => $t->where('is_sensitive_location', true));
                            });
                        if ($defaultGeo === GeoprivacyLevel::Obscured->value) {
                            $cond->orWhere('specimens.geoprivacy', GeoprivacyLevel::Inherit->value);
                        }
                    });
            });
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $taxon = $this->currentDetermination?->taxon;

        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'catalog_number' => $this->catalog_number,
            'field_number' => $this->field_number,
            'recorded_by' => $this->recorded_by,
            'country' => $this->country,
            'state_province' => $this->state_province,
            'scientific_name' => $taxon?->scientific_name,
            'family' => $taxon?->family,
            'genus' => $taxon?->genus,
            'visibility' => $this->visibility?->value,
        ];
    }
}
