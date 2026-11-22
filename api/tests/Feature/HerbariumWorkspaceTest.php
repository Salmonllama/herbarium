<?php

namespace Tests\Feature;

use App\Enums\HerbariumRole;
use App\Models\HerbariumMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HerbariumWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_configure_and_manage_herbarium_rbac(): void
    {
        $owner = User::factory()->create();
        $curator = User::factory()->create();
        $outsider = User::factory()->create();

        Sanctum::actingAs($owner);

        $createRes = $this->postJson('/api/v1/herbaria', [
            'name' => 'Cascade Botanical Collection',
            'institution_code' => 'CASC',
            'visibility' => 'private',
            'id_prefix' => 'CASC',
            'id_separator' => '-',
            'id_padding' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'cascade-botanical-collection')
            ->assertJsonPath('data.next_catalog_number_preview', 'CASC-00001')
            ->assertJsonPath('data.current_user_role', 'owner');

        $slug = $createRes->json('data.slug');

        // Private herbarium is hidden from outsiders
        Sanctum::actingAs($outsider);
        $this->getJson("/api/v1/herbaria/{$slug}")->assertForbidden();

        // Owner invites curator
        Sanctum::actingAs($owner);
        $inviteRes = $this->postJson("/api/v1/herbaria/{$slug}/invitations", [
            'email' => $curator->email,
            'role' => 'curator',
        ])->assertCreated();

        $token = $inviteRes->json('data.token');

        // Curator accepts invitation
        Sanctum::actingAs($curator);
        $this->postJson("/api/v1/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('role', 'curator');

        // Curator can view private herbarium, but cannot update settings
        $this->getJson("/api/v1/herbaria/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.current_user_role', 'curator');

        $this->patchJson("/api/v1/herbaria/{$slug}", [
            'name' => 'Unauthorized Rename',
        ])->assertForbidden();

        // Owner can update custom domain and ID prefix, and cannot demote last owner
        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/herbaria/{$slug}", [
            'custom_domain' => 'herbarium.cascade.edu',
            'id_prefix' => 'CBC',
            'id_padding' => 4,
        ])->assertOk()
            ->assertJsonPath('data.next_catalog_number_preview', 'CBC-0001');

        $ownerMembership = HerbariumMembership::query()
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->patchJson("/api/v1/herbaria/{$slug}/members/{$ownerMembership->id}", [
            'role' => HerbariumRole::Curator->value,
        ])->assertStatus(422);

        // Custom domain resolves via /api/v1/instance
        $this->withHeader('X-Forwarded-Host', 'herbarium.cascade.edu')
            ->getJson('/api/v1/instance')
            ->assertOk()
            ->assertJsonPath('default_herbarium_slug', $slug);
    }
}
