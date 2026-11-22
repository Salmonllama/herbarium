<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HerbariumRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\HerbariumMemberResource;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class HerbariumMemberController extends Controller
{
    public function index(Herbarium $herbarium): JsonResponse
    {
        Gate::authorize('manageMembers', $herbarium);

        $members = $herbarium->memberships()->with('user')->orderBy('created_at')->get();

        return HerbariumMemberResource::collection($members)->response();
    }

    public function update(Request $request, Herbarium $herbarium, HerbariumMembership $membership): JsonResponse
    {
        Gate::authorize('manageMembers', $herbarium);

        abort_unless($membership->herbarium_id === $herbarium->id, 404);

        $validated = $request->validate([
            'role' => ['sometimes', Rule::enum(HerbariumRole::class)],
            'can_view_exact_coordinates' => ['sometimes', 'boolean'],
        ]);

        if (
            isset($validated['role'])
            && $membership->role === HerbariumRole::Owner
            && $validated['role'] !== HerbariumRole::Owner->value
            && $herbarium->memberships()->where('role', HerbariumRole::Owner->value)->count() <= 1
        ) {
            return response()->json([
                'message' => 'Cannot demote the last remaining owner of a herbarium.',
            ], 422);
        }

        $membership->update($validated);

        return (new HerbariumMemberResource($membership->fresh('user')))->response();
    }

    public function destroy(Herbarium $herbarium, HerbariumMembership $membership): JsonResponse
    {
        Gate::authorize('manageMembers', $herbarium);

        abort_unless($membership->herbarium_id === $herbarium->id, 404);

        if (
            $membership->role === HerbariumRole::Owner
            && $herbarium->memberships()->where('role', HerbariumRole::Owner->value)->count() <= 1
        ) {
            return response()->json([
                'message' => 'Cannot remove the last remaining owner of a herbarium.',
            ], 422);
        }

        $membership->delete();

        return response()->json(['message' => 'Member removed.']);
    }
}
