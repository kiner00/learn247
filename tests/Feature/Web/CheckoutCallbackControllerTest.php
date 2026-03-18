<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use App\Http\Controllers\Web\GuestCheckoutController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    // ── __invoke (signed URL callback) ────────────────────────────────────────

    public function test_valid_signed_url_logs_in_user_and_renders_processing(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $url = GuestCheckoutController::buildCallbackUrl($user->id, $community->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CheckoutProcessing')
                ->where('communitySlug', $community->slug)
                ->where('communityName', $community->name)
            );

        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_link_returns_403(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        // Build a valid token but with an expired timestamp
        $expires  = now()->subHour()->getTimestamp();
        $expected = hash_hmac('sha256', "{$user->id}|{$community->slug}|{$expires}", config('app.key'));

        $url = route('checkout.callback', [
            'user'      => $user->id,
            'community' => $community->slug,
            'expires'   => $expires,
            'token'     => $expected,
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_invalid_signature_returns_403(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        // Build a URL with a bad token
        $expires = now()->addHours(2)->getTimestamp();
        $url = route('checkout.callback', [
            'user'      => $user->id,
            'community' => $community->slug,
            'expires'   => $expires,
            'token'     => 'invalid-token',
        ]);

        $this->get($url)->assertForbidden();
    }

    public function test_callback_with_nonexistent_user_returns_404(): void
    {
        $community = Community::factory()->create();

        $url = GuestCheckoutController::buildCallbackUrl(99999, $community->slug);

        $this->get($url)->assertNotFound();
    }

    public function test_callback_with_nonexistent_community_returns_404(): void
    {
        $user = User::factory()->create();

        $url = GuestCheckoutController::buildCallbackUrl($user->id, 'nonexistent-slug');

        $this->get($url)->assertNotFound();
    }

    // ── status ────────────────────────────────────────────────────────────────

    public function test_status_returns_active_true_when_subscription_active(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $this->actingAs($user)
            ->getJson(route('checkout.status', $community->slug))
            ->assertOk()
            ->assertJson(['active' => true]);
    }

    public function test_status_returns_active_false_when_no_active_subscription(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->getJson(route('checkout.status', $community->slug))
            ->assertOk()
            ->assertJson(['active' => false]);
    }

    public function test_status_returns_false_when_no_subscription_exists(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->actingAs($user)
            ->getJson(route('checkout.status', $community->slug))
            ->assertOk()
            ->assertJson(['active' => false]);
    }

    public function test_status_requires_auth(): void
    {
        $community = Community::factory()->create();

        $this->getJson(route('checkout.status', $community->slug))
            ->assertUnauthorized();
    }

    // ── ref_code cookie affiliate pixel branch ────────────────────────────────

    public function test_callback_with_ref_code_cookie_passes_affiliate_pixels(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['facebook_pixel_id' => null]);

        $affiliate = \App\Models\Affiliate::create([
            'community_id'      => $community->id,
            'user_id'           => $community->owner_id,
            'code'              => 'REF-PIXEL',
            'status'            => \App\Models\Affiliate::STATUS_ACTIVE,
            'facebook_pixel_id' => 'FB_123',
            'tiktok_pixel_id'   => 'TT_456',
        ]);

        $url = GuestCheckoutController::buildCallbackUrl($user->id, $community->slug);

        $this->withCookie('ref_code', $affiliate->code)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CheckoutProcessing')
                ->where('affiliateFbPixelId', 'FB_123')
                ->where('affiliateTiktokPixelId', 'TT_456')
            );
    }

    public function test_callback_with_unknown_ref_code_cookie_passes_null_pixels(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();

        $url = GuestCheckoutController::buildCallbackUrl($user->id, $community->slug);

        $this->withCookie('ref_code', 'NONEXISTENT-CODE')
            ->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CheckoutProcessing')
                ->where('affiliateFbPixelId', null)
            );
    }
}
