<?php

namespace App\Http\Resources;

use App\Contracts\PlanLimitsContract;
use App\Models\Herbarium;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Herbarium */
class HerbariumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $membership = $user?->membershipFor($this->resource);
        $isOwner = $user && ($user->is_instance_admin || $membership?->role->canManageHerbarium());

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'institution_code' => $this->institution_code,
            'custom_domain' => $this->custom_domain,
            'description' => $this->description,
            'contact_email' => $this->contact_email,
            'website_url' => $this->website_url,
            'visibility' => $this->visibility->value,
            'id_prefix' => $this->id_prefix,
            'id_separator' => $this->id_separator,
            'id_padding' => $this->id_padding,
            'id_next_sequence' => $this->when($membership !== null || $user?->is_instance_admin, $this->id_next_sequence),
            'next_catalog_number_preview' => $this->when($membership !== null || $user?->is_instance_admin, fn () => $this->previewNextCatalogNumber()),
            'default_geoprivacy' => $this->default_geoprivacy->value,
            'default_license' => $this->default_license,
            'current_user_role' => $membership?->role->value ?? ($user?->is_instance_admin ? 'owner' : null),
            'can_view_exact_coordinates' => $user ? $user->canViewExactCoordinatesIn($this->resource) : false,
            'counts' => $this->when(
                isset($this->specimens_count) || isset($this->taxa_count) || isset($this->published_specimens_count),
                fn () => [
                    'specimens' => $this->specimens_count ?? null,
                    'published_specimens' => $this->published_specimens_count ?? null,
                    'taxa' => $this->taxa_count ?? null,
                ],
            ),
            'plan_limits' => $this->when($isOwner, fn () => app(PlanLimitsContract::class)->describeLimits($this->resource)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
