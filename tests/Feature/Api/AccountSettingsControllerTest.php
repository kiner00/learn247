<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_account_settings_returns_settings_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/account/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'tab', 'profileUser', 'memberships', 'timezone', 'theme',
                'notifPrefs', 'chatPrefs',
            ]);
    }

    public function test_show_with_tab_parameter_returns_tab_in_response(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/account/settings?tab=notifications');

        $response->assertOk()->assertJsonPath('tab', 'notifications');
    }

    public function test_update_theme_validation_rejects_invalid_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/account/settings/theme', [
            'theme' => 'invalid-theme',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['theme']);
    }

    public function test_update_timezone_validation_rejects_invalid_timezone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/account/settings/timezone', [
            'timezone' => 'Invalid/Timezone',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['timezone']);
    }

    public function test_patch_email_updates_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this->actingAs($user)->patchJson('/api/account/settings/email', [
            'email' => 'new@example.com',
        ]);

        $response->assertOk()->assertJson(['message' => 'Email updated.']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@example.com']);
    }

    public function test_patch_password_updates_password(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/account/settings/password', [
            'current_password'      => 'password',
            'password'              => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertOk()->assertJson(['message' => 'Password updated.']);
    }

    public function test_patch_timezone_updates_timezone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/account/settings/timezone', [
            'timezone' => 'Asia/Manila',
        ]);

        $response->assertOk()->assertJson(['message' => 'Timezone updated.']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'timezone' => 'Asia/Manila']);
    }

    public function test_patch_theme_updates_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/api/account/settings/theme', [
            'theme' => 'dark',
        ]);

        $response->assertOk()->assertJson(['message' => 'Theme updated.']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'theme' => 'dark']);
    }

    public function test_post_logout_everywhere_revokes_other_sessions(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/account/settings/logout-everywhere');

        $response->assertOk()->assertJson(['message' => 'All other sessions have been logged out.']);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/account/settings')->assertUnauthorized();
        $this->patchJson('/api/account/settings/email', ['email' => 'test@example.com'])->assertUnauthorized();
    }

    public function test_patch_notifications_updates_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/account/settings/notifications', [
            'email_notifications' => false,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Notification preferences updated.']);
    }

    public function test_patch_community_notifications_updates_preferences(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->patchJson("/api/account/settings/notifications/{$community->id}", [
            'muted' => true,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Community notification preferences updated.']);
    }

    public function test_patch_chat_updates_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/account/settings/chat', [
            'chat_enabled' => true,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Chat preferences updated.']);
    }

    public function test_patch_community_chat_updates_preferences(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->patchJson("/api/account/settings/chat/{$community->id}", [
            'chat_enabled' => false,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Community chat preferences updated.']);
    }

    public function test_patch_payout_updates_payout_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/account/settings/payout', [
            'payout_method'   => 'gcash',
            'payout_account' => '09123456789',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Payout details updated.']);
    }

    public function test_patch_crypto_updates_wallet(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/account/settings/crypto', [
            'crypto_wallet' => '0x1234567890abcdef',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Crypto wallet updated.']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'crypto_wallet' => '0x1234567890abcdef']);
    }

    public function test_patch_profile_updates_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/account/settings/profile', [
            'username'   => 'updateduser',
            'first_name' => 'Updated',
            'last_name'  => 'Name',
            'bio'        => 'Updated bio',
        ])
            ->assertOk()
            ->assertJson(['message' => 'Profile updated.']);
    }

    public function test_patch_membership_visibility(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)->patchJson("/api/account/settings/profile/visibility/{$community->id}", [
            'is_public' => false,
        ])
            ->assertOk()
            ->assertJson(['message' => 'Visibility updated.']);
    }
}
