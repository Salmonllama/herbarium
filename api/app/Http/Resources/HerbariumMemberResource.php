<?php

namespace App\Http\Resources;

use App\Models\HerbariumMembership;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HerbariumMembership */
class HerbariumMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'role' => $this->role->value,
            'can_view_exact_coordinates' => $this->can_view_exact_coordinates,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
