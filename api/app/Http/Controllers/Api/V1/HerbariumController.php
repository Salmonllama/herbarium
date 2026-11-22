<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PlanLimitsContract;
use App\Enums\GeoprivacyLevel;
use App\Enums\HerbariumRole;
use App\Enums\HerbariumVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\HerbariumResource;
use App\Models\Herbarium;
use App\Models\HerbariumMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HerbariumController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $mineOnly = $request->boolean('mine');

        $query = Herbarium::query()->orderBy('name');

        if ($mineOnly && $user) {
            $query->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id));
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('visibility', HerbariumVisibility::Public->value);
                if ($user) {
                    $q->orWhereHas('memberships', fn ($m) => $m->where('user_id', $user->id));
                }
            });
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('institution_code', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        if (method_exists(Herbarium::class, 'specimens')) {
            $query->withCount([
                'specimens as published_specimens_count' => fn ($q) => $q->where('visibility', 'published'),
                'taxa',
            ]);
        }

        $herbaria = $query->paginate(min(50, max(1, (int) $request->input('per_page', 20))));

        return HerbariumResource::collection($herbaria)->response();
    }

    public function store(Request $request, PlanLimitsContract $planLimits): JsonResponse
    {
        $user = $request->user();

        if (! $planLimits->canCreateHerbarium($user)) {
            return response()->json([
                'message' => 'Your current plan has reached the maximum number of herbaria.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:herbaria,slug'],
            'institution_code' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'visibility' => ['sometimes', Rule::enum(HerbariumVisibility::class)],
            'id_prefix' => ['sometimes', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'id_separator' => ['sometimes', 'nullable', 'string', 'max:4'],
            'id_padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'id_next_sequence' => ['sometimes', 'integer', 'min:1'],
            'default_geoprivacy' => ['sometimes', Rule::in([GeoprivacyLevel::Exact->value, GeoprivacyLevel::Obscured->value, GeoprivacyLevel::Hidden->value])],
            'default_license' => ['sometimes', 'string', 'max:64'],
        ]);

        $slug = $validated['slug'] ?? $this->generateUniqueSlug($validated['name']);

        $herbarium = DB::transaction(function () use ($validated, $slug, $user) {
            $herbarium = Herbarium::create([
                'slug' => $slug,
                'name' => $validated['name'],
                'institution_code' => $validated['institution_code'] ?? null,
                'description' => $validated['description'] ?? null,
                'contact_email' => $validated['contact_email'] ?? $user->email,
                'website_url' => $validated['website_url'] ?? null,
                'visibility' => $validated['visibility'] ?? HerbariumVisibility::Public->value,
                'id_prefix' => strtoupper($validated['id_prefix'] ?? Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $slug) ?: 'HERB', 0, 6)),
                'id_separator' => $validated['id_separator'] ?? '-',
                'id_padding' => $validated['id_padding'] ?? 6,
                'id_next_sequence' => $validated['id_next_sequence'] ?? 1,
                'default_geoprivacy' => $validated['default_geoprivacy'] ?? GeoprivacyLevel::Exact->value,
                'default_license' => $validated['default_license'] ?? 'CC-BY-4.0',
                'created_by' => $user->id,
            ]);

            HerbariumMembership::create([
                'herbarium_id' => $herbarium->id,
                'user_id' => $user->id,
                'role' => HerbariumRole::Owner,
                'can_view_exact_coordinates' => true,
            ]);

            return $herbarium;
        });

        return (new HerbariumResource($herbarium))->response()->setStatusCode(201);
    }

    public function show(Request $request, Herbarium $herbarium): JsonResponse
    {
        $user = $request->user('sanctum');
        if (! Gate::forUser($user)->allows('view', $herbarium)) {
            return response()->json(['message' => 'Herbarium not found or private.'], 403);
        }

        if (method_exists($herbarium, 'specimens')) {
            $herbarium->loadCount([
                'specimens',
                'specimens as published_specimens_count' => fn ($q) => $q->where('visibility', 'published'),
                'taxa',
            ]);
        }

        return (new HerbariumResource($herbarium))->response();
    }

    public function update(Request $request, Herbarium $herbarium, PlanLimitsContract $planLimits): JsonResponse
    {
        Gate::authorize('update', $herbarium);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'institution_code' => ['nullable', 'string', 'max:32'],
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i', Rule::unique('herbaria', 'custom_domain')->ignore($herbarium->id)],
            'description' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'visibility' => ['sometimes', Rule::enum(HerbariumVisibility::class)],
            'id_prefix' => ['sometimes', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'id_separator' => ['sometimes', 'nullable', 'string', 'max:4'],
            'id_padding' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'id_next_sequence' => ['sometimes', 'integer', 'min:1'],
            'default_geoprivacy' => ['sometimes', Rule::in([GeoprivacyLevel::Exact->value, GeoprivacyLevel::Obscured->value, GeoprivacyLevel::Hidden->value])],
            'default_license' => ['sometimes', 'string', 'max:64'],
        ]);

        if (! empty($validated['custom_domain']) && ! $planLimits->canUseCustomDomain($herbarium)) {
            return response()->json(['message' => 'Custom domains are not enabled on your current plan.'], 403);
        }

        if (array_key_exists('id_separator', $validated) && $validated['id_separator'] === null) {
            $validated['id_separator'] = '';
        }

        $herbarium->update($validated);

        return (new HerbariumResource($herbarium->fresh()))->response();
    }

    public function destroy(Herbarium $herbarium): JsonResponse
    {
        Gate::authorize('delete', $herbarium);
        $herbarium->delete();

        return response()->json(['message' => 'Herbarium archived.']);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'herbarium';
        $slug = $base;
        $suffix = 2;

        while (Herbarium::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
