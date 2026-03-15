<?php

namespace Tests\Feature\Api;

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
}
