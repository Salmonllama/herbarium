<?php

namespace App\Http\Resources;

use App\Models\HerbariumInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HerbariumInvitation */
class HerbariumInvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'herbarium_id' => $this->herbarium_id,
            'email' => $this->email,
            'role' => $this->role->value,
            'can_view_exact_coordinates' => $this->can_view_exact_coordinates,
            'token' => $this->when($request->user() !== null, $this->token),
            'is_pending' => $this->isPending(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'herbarium' => new HerbariumResource($this->whenLoaded('herbarium')),
        ];
    }
}
