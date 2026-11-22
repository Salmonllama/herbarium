<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HerbariumRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\HerbariumInvitationResource;
use App\Models\Herbarium;
use App\Models\HerbariumInvitation;
use App\Models\HerbariumMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HerbariumInvitationController extends Controller
{
    public function index(Herbarium $herbarium): JsonResponse
    {
        Gate::authorize('manageMembers', $herbarium);

        $invitations = $herbarium->invitations()->latest()->get();

        return HerbariumInvitationResource::collection($invitations)->response();
    }

    public function store(Request $request, Herbarium $herbarium): JsonResponse
    {
        Gate::authorize('manageMembers', $herbarium);

        $validated = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'role' => ['required', Rule::enum(HerbariumRole::class)],
            'can_view_exact_coordinates' => ['sometimes', 'boolean'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:60'],
        ]);

        $role = HerbariumRole::from($validated['role']);
        $canViewExact = array_key_exists('can_view_exact_coordinates', $validated)
            ? (bool) $validated['can_view_exact_coordinates']
            : $role->defaultExactCoordinates();

        $invitation = HerbariumInvitation::create([
            'herbarium_id' => $herbarium->id,
            'email' => $validated['email'],
            'role' => $role,
            'can_view_exact_coordinates' => $canViewExact,
            'token' => Str::random(48),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays((int) ($validated['expires_in_days'] ?? 14)),
        ]);

        return (new HerbariumInvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function destroy(Herbarium $herbarium, HerbariumInvitation $invitation): JsonResponse
    {
        Gate::authorize('manageMembers', $herbarium);
        abort_unless($invitation->herbarium_id === $herbarium->id, 404);

        $invitation->delete();

        return response()->json(['message' => 'Invitation revoked.']);
    }

    public function showByToken(string $token): JsonResponse
    {
        $invitation = HerbariumInvitation::query()
            ->with('herbarium')
            ->where('token', $token)
            ->firstOrFail();

        if (! $invitation->isPending()) {
            return response()->json(['message' => 'This invitation is no longer valid.'], 410);
        }

        return (new HerbariumInvitationResource($invitation))->response();
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = HerbariumInvitation::query()
            ->with('herbarium')
            ->where('token', $token)
            ->firstOrFail();

        if (! $invitation->isPending()) {
            return response()->json(['message' => 'This invitation is no longer valid.'], 410);
        }

        $user = $request->user();

        DB::transaction(function () use ($invitation, $user) {
            HerbariumMembership::updateOrCreate(
                [
                    'herbarium_id' => $invitation->herbarium_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $invitation->role,
                    'can_view_exact_coordinates' => $invitation->can_view_exact_coordinates,
                ],
            );

            $invitation->update(['accepted_at' => now()]);
        });

        return response()->json([
            'message' => 'Invitation accepted.',
            'herbarium_slug' => $invitation->herbarium->slug,
            'role' => $invitation->role->value,
        ]);
    }
}
