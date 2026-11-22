<?php

namespace Tests\Feature;

use App\Enums\HerbariumRole;
use App\Models\Herbarium;
use App\Models\HerbariumInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndInstanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_and_instance_endpoints_work(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database', true);

        $this->getJson('/api/v1/instance')
            ->assertOk()
            ->assertJsonPath('registration_mode', 'open');
    }

    public function test_first_registered_user_becomes_instance_admin_and_can_manage_tokens(): void
    {
        $response = $this->postJson('/auth/register', [
            'name' => 'Dr. Ada Botanist',
            'email' => 'ada@herbarium.edu',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'institution' => 'Pacific State University',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.is_instance_admin', true);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'ada@herbarium.edu');

        $tokenResponse = $this->postJson('/api/v1/tokens', [
            'name' => 'GBIF Sync Script',
            'abilities' => ['read'],
        ])->assertCreated();

        $plainToken = $tokenResponse->json('token.plain_text_token');
        $this->assertNotEmpty($plainToken);

        $this->postJson('/auth/logout')->assertOk();

        // Bearer token works independently of session cookie
        $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'ada@herbarium.edu');
    }

    public function test_invite_only_registration_blocks_uninvited_users_but_allows_invited(): void
    {
        $admin = User::factory()->create(['is_instance_admin' => true]);
        config()->set('herbarium.registration_mode', 'invite_only');

        $this->postJson('/auth/register', [
            'name' => 'Uninvited User',
            'email' => 'stranger@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])->assertForbidden();

        $herbarium = Herbarium::create([
            'slug' => 'psu-flora',
            'name' => 'PSU Herbarium',
            'created_by' => $admin->id,
        ]);

        $invitation = HerbariumInvitation::create([
            'herbarium_id' => $herbarium->id,
            'email' => 'student@example.com',
            'role' => HerbariumRole::Contributor,
            'can_view_exact_coordinates' => false,
            'token' => 'invite-token-123456',
            'invited_by' => $admin->id,
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/auth/register', [
            'name' => 'Field Student',
            'email' => 'student@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'invitation_token' => $invitation->token,
        ])->assertCreated()
            ->assertJsonPath('user.is_instance_admin', false);

        $this->assertDatabaseHas('herbarium_memberships', [
            'herbarium_id' => $herbarium->id,
            'role' => 'contributor',
        ]);
    }
}
