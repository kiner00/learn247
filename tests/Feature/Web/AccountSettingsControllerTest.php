<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── show ──────────────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get('/account/settings')->assertRedirect('/login');
    }

    public function test_show_returns_inertia_settings_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/account/settings');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Account/Settings')
            ->has('profileUser')
            ->has('memberships')
            ->has('timezone')
            ->has('theme')
            ->has('notifPrefs')
            ->has('chatPrefs')
        );
    }

    public function test_show_accepts_tab_query_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/account/settings?tab=notifications');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tab', 'notifications')
        );
    }

    // ─── updateProfile ─────────────────────────────────────────────────────────

    public function test_update_profile_basic_fields(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'username' => 'oldusername']);

        $response = $this->actingAs($user)->patch('/account/settings/profile', [
            'username'   => 'newusername',
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'bio'        => 'My bio',
            'location'   => 'Manila',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated!');

        $user->refresh();
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('My bio', $user->bio);
        $this->assertEquals('Manila', $user->location);
    }

    public function test_update_profile_with_avatar_upload(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create(['username' => 'janedoe']);

        $response = $this->actingAs($user)->post('/account/settings/profile', [
            'username'   => 'janedoe',
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'avatar'     => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Profile updated!');

        $user->refresh();
        $this->assertNotNull($user->avatar);
    }

    public function test_update_profile_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/profile', []);

        $response->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_update_profile_validates_avatar_is_image(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/profile', [
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
            'avatar'     => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors(['avatar']);
    }

    // ─── updateMembershipVisibility ────────────────────────────────────────────

    public function test_update_membership_visibility(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/account/settings/profile/visibility/{$community->id}", [
                'show_on_profile' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Membership visibility updated!');
    }

    public function test_update_membership_visibility_requires_boolean(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/account/settings/profile/visibility/{$community->id}", [
                'show_on_profile' => 'not-a-bool',
            ]);

        $response->assertSessionHasErrors(['show_on_profile']);
    }

    // ─── updateEmail ───────────────────────────────────────────────────────────

    public function test_update_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this->actingAs($user)->patch('/account/settings/email', [
            'email' => 'new@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Email updated!');
        $this->assertEquals('new@example.com', $user->fresh()->email);
    }

    public function test_update_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $response = $this->actingAs($user)->patch('/account/settings/email', [
            'email' => 'taken@example.com',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_update_email_allows_keeping_own_email(): void
    {
        $user = User::factory()->create(['email' => 'mine@example.com']);

        $response = $this->actingAs($user)->patch('/account/settings/email', [
            'email' => 'mine@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Email updated!');
    }

    public function test_update_email_validates_format(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/email', [
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    // ─── updatePassword ────────────────────────────────────────────────────────

    public function test_update_password_with_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->patch('/account/settings/password', [
            'current_password'      => 'old-password',
            'password'              => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Password updated!');
        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
    }

    public function test_update_password_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $response = $this->actingAs($user)->patch('/account/settings/password', [
            'current_password' => 'old-password',
            'password'         => 'new-secure-password',
        ]);

        $response->assertSessionHasErrors(['password']);
    }

    public function test_update_password_requires_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/password', []);

        $response->assertSessionHasErrors(['current_password', 'password']);
    }

    // ─── updateTimezone ────────────────────────────────────────────────────────

    public function test_update_timezone(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/timezone', [
            'timezone' => 'America/New_York',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Timezone saved!');
        $this->assertEquals('America/New_York', $user->fresh()->timezone);
    }

    public function test_update_timezone_validates_timezone_string(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/timezone', [
            'timezone' => 'Invalid/Zone',
        ]);

        $response->assertSessionHasErrors(['timezone']);
    }

    public function test_update_timezone_requires_field(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/timezone', []);

        $response->assertSessionHasErrors(['timezone']);
    }

    // ─── logoutEverywhere ──────────────────────────────────────────────────────

    public function test_logout_everywhere(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/account/settings/logout-everywhere');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Logged out of all other devices.');
    }

    // ─── updateNotifications ───────────────────────────────────────────────────

    public function test_update_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/notifications', [
            'follower'  => true,
            'likes'     => false,
            'kaching'   => true,
            'affiliate' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Notification preferences saved!');
    }

    public function test_update_notifications_requires_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/notifications', [
            'follower' => true,
        ]);

        $response->assertSessionHasErrors(['likes', 'kaching', 'affiliate']);
    }

    // ─── updateCommunityNotifications ──────────────────────────────────────────

    public function test_update_community_notifications(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/account/settings/notifications/{$community->id}", [
                'new_posts' => true,
                'comments'  => false,
                'mentions'  => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Community notification preferences saved!');
    }

    public function test_update_community_notifications_requires_all_fields(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/account/settings/notifications/{$community->id}", [
                'new_posts' => true,
            ]);

        $response->assertSessionHasErrors(['comments', 'mentions']);
    }

    // ─── updateChat ────────────────────────────────────────────────────────────

    public function test_update_chat(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/chat', [
            'notifications'       => true,
            'email_notifications' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Chat preferences saved!');
    }

    public function test_update_chat_requires_all_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/chat', [
            'notifications' => true,
        ]);

        $response->assertSessionHasErrors(['email_notifications']);
    }

    // ─── updateCommunityChat ───────────────────────────────────────────────────

    public function test_update_community_chat(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/account/settings/chat/{$community->id}", [
                'chat_enabled' => false,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Chat preference saved!');
    }

    public function test_update_community_chat_requires_boolean(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->patch("/account/settings/chat/{$community->id}", [
                'chat_enabled' => 'not-a-bool',
            ]);

        $response->assertSessionHasErrors(['chat_enabled']);
    }

    // ─── updateTheme ───────────────────────────────────────────────────────────

    public function test_update_theme_to_dark(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/theme', [
            'theme' => 'dark',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Theme saved!');
        $this->assertEquals('dark', $user->fresh()->theme);
    }

    public function test_update_theme_to_light(): void
    {
        $user = User::factory()->create(['theme' => 'dark']);

        $response = $this->actingAs($user)->patch('/account/settings/theme', [
            'theme' => 'light',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Theme saved!');
        $this->assertEquals('light', $user->fresh()->theme);
    }

    public function test_update_theme_rejects_invalid_value(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/theme', [
            'theme' => 'blue',
        ]);

        $response->assertSessionHasErrors(['theme']);
    }

    // ─── updatePayout ──────────────────────────────────────────────────────────

    public function test_update_payout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/payout', [
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Payout details saved!');
        $this->assertEquals('gcash', $user->fresh()->payout_method);
        $this->assertEquals('09171234567', $user->fresh()->payout_details);
    }

    public function test_update_payout_validates_allowed_methods(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/payout', [
            'payout_method'  => 'venmo',
            'payout_details' => 'user@venmo',
        ]);

        $response->assertSessionHasErrors(['payout_method']);
    }

    public function test_update_payout_requires_both_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/payout', []);

        $response->assertSessionHasErrors(['payout_method', 'payout_details']);
    }

    public function test_update_payout_accepts_all_valid_methods(): void
    {
        $user = User::factory()->create();

        foreach (['gcash', 'maya', 'bank', 'paypal'] as $method) {
            $response = $this->actingAs($user)->patch('/account/settings/payout', [
                'payout_method'  => $method,
                'payout_details' => 'details-for-' . $method,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');
        }
    }

    // ─── updateCrypto ──────────────────────────────────────────────────────────

    public function test_update_crypto_wallet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/crypto', [
            'crypto_wallet' => '0xABC123DEF456',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Crypto wallet saved!');
        $this->assertEquals('0xABC123DEF456', $user->fresh()->crypto_wallet);
    }

    public function test_update_crypto_wallet_nullable(): void
    {
        $user = User::factory()->create(['crypto_wallet' => '0xOldWallet']);

        $response = $this->actingAs($user)->patch('/account/settings/crypto', [
            'crypto_wallet' => null,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Crypto wallet saved!');
        $this->assertNull($user->fresh()->crypto_wallet);
    }

    public function test_update_crypto_wallet_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/account/settings/crypto', [
            'crypto_wallet' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors(['crypto_wallet']);
    }
}
