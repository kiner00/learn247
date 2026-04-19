<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\GuestCheckout;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // ─── findOrCreateUser ────────────────────────────────────────────────────────

    public function test_creates_new_user_with_needs_password_setup(): void
    {
        $action = app(GuestCheckout::class);

        $user = $action->findOrCreateUser([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
        ]);

        $this->assertTrue($user->needs_password_setup);
        $this->assertEquals('Juan Cruz', $user->name);
        $this->assertEquals('juan@example.com', $user->email);
        $this->assertEquals('09171234567', $user->phone);
        $this->assertNotNull($user->username);
    }

    public function test_returns_existing_user_without_modifying_it(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.com',
            'name' => 'Old Name',
            'needs_password_setup' => false,
        ]);

        $action = app(GuestCheckout::class);
        $user = $action->findOrCreateUser([
            'first_name' => 'New',
            'last_name' => 'Name',
            'email' => 'existing@example.com',
            'phone' => '09170000000',
        ]);

        $this->assertEquals($existing->id, $user->id);
        $this->assertEquals('Old Name', $user->name);
    }

    public function test_generated_username_uses_first_and_last_name(): void
    {
        $action = app(GuestCheckout::class);

        $user = $action->findOrCreateUser([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.com',
            'phone' => '09181234567',
        ]);

        $this->assertStringStartsWith('maria-santos-', $user->username);
    }

    public function test_generated_username_handles_special_characters(): void
    {
        $action = app(GuestCheckout::class);

        $user = $action->findOrCreateUser([
            'first_name' => 'José',
            'last_name' => 'García',
            'email' => 'jose@example.com',
            'phone' => '09191234567',
        ]);

        // Username should only contain alphanumeric and dashes
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $user->username);
        $this->assertNotNull($user->username);
    }

    public function test_generated_username_handles_empty_last_name_characters(): void
    {
        $action = app(GuestCheckout::class);

        // Last name with only special characters should not crash
        $user = $action->findOrCreateUser([
            'first_name' => 'Test',
            'last_name' => '!!!',
            'email' => 'test-special@example.com',
            'phone' => '09001234567',
        ]);

        $this->assertNotNull($user->username);
        $this->assertMatchesRegularExpression('/^[a-z0-9\-]+$/', $user->username);
    }

    // ─── hasActiveSubscription ───────────────────────────────────────────────────

    public function test_returns_true_when_user_has_active_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->active()->create([
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);

        $action = app(GuestCheckout::class);

        $this->assertTrue($action->hasActiveSubscription($user->id, $community->id));
    }

    public function test_returns_false_when_user_has_no_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $action = app(GuestCheckout::class);

        $this->assertFalse($action->hasActiveSubscription($user->id, $community->id));
    }

    public function test_returns_false_when_subscription_is_pending(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'community_id' => $community->id,
            'status' => Subscription::STATUS_PENDING,
        ]);

        $action = app(GuestCheckout::class);

        $this->assertFalse($action->hasActiveSubscription($user->id, $community->id));
    }

    public function test_returns_false_when_subscription_is_expired(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->expired()->create([
            'user_id' => $user->id,
            'community_id' => $community->id,
        ]);

        $action = app(GuestCheckout::class);

        $this->assertFalse($action->hasActiveSubscription($user->id, $community->id));
    }

    public function test_returns_false_for_active_subscription_to_different_community(): void
    {
        $user = User::factory()->create();
        $community1 = Community::factory()->paid()->create();
        $community2 = Community::factory()->paid()->create();

        Subscription::factory()->active()->create([
            'user_id' => $user->id,
            'community_id' => $community1->id,
        ]);

        $action = app(GuestCheckout::class);

        $this->assertFalse($action->hasActiveSubscription($user->id, $community2->id));
    }
}
