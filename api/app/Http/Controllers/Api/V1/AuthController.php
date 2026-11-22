<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\HerbariumInvitation;
use App\Models\HerbariumMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'institution' => ['nullable', 'string', 'max:255'],
            'orcid_id' => ['nullable', 'string', 'max:64', 'unique:users,orcid_id'],
            'invitation_token' => ['nullable', 'string', 'max:64'],
        ]);

        $invitation = null;
        if (! empty($validated['invitation_token'])) {
            $invitation = HerbariumInvitation::query()
                ->where('token', $validated['invitation_token'])
                ->first();

            if (! $invitation || ! $invitation->isPending()) {
                throw ValidationException::withMessages([
                    'invitation_token' => ['This invitation is invalid or has expired.'],
                ]);
            }
        }

        $registrationMode = config('herbarium.registration_mode', 'open');
        if ($registrationMode === 'invite_only' && ! $invitation && User::query()->exists()) {
            return response()->json([
                'message' => 'Registration on this Herbarium instance is invite-only.',
            ], 403);
        }

        $user = DB::transaction(function () use ($validated, $invitation) {
            $isFirstUser = ! User::query()->exists();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'institution' => $validated['institution'] ?? null,
                'orcid_id' => $validated['orcid_id'] ?? null,
                'is_instance_admin' => $isFirstUser,
            ]);

            if ($invitation) {
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
            }

            return $user;
        });

        Auth::guard('web')->login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->where('email', strtolower($validated['email']))->first();

        if (! $user || ! $user->password || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        Auth::guard('web')->login($user, (bool) ($validated['remember'] ?? false));
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('memberships.herbarium');

        return response()->json([
            'user' => new UserResource($user),
            'herbaria' => $user->memberships->map(fn ($m) => [
                'id' => $m->herbarium->id,
                'slug' => $m->herbarium->slug,
                'name' => $m->herbarium->name,
                'institution_code' => $m->herbarium->institution_code,
                'role' => $m->role->value,
                'can_view_exact_coordinates' => $m->can_view_exact_coordinates,
            ])->values(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'orcid_id' => ['nullable', 'string', 'max:64', \Illuminate\Validation\Rule::unique('users', 'orcid_id')->ignore($user?->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
