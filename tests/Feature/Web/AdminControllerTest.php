<?php

namespace Tests\Feature\Web;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Mail\TempPasswordMail;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Coupon;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['is_super_admin' => true]);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['is_super_admin' => false]);
    }

    private function mockXendit(): MockInterface
    {
        return $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getBalance')->andReturn(50000.00);
            $mock->shouldReceive('createPayout')->andReturn(['id' => 'payout_fake_123']);
        });
    }

    // ── Middleware ────────────────────────────────────────────────────────────

    public function test_non_super_admin_gets_403(): void
    {
        $user = $this->regularUser();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }

    // ── dashboard ────────────────────────────────────────────────────────────

    public function test_dashboard_renders_for_super_admin(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('stats')
            ->has('revenue')
            ->has('byCategory')
            ->has('recentCommunities')
            ->has('recentUsers')
            ->has('pendingOnboarding')
            ->has('xenditBalance')
        );
    }

    public function test_dashboard_includes_correct_stats(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('stats.total_users', User::count())
            ->where('stats.total_communities', Community::count())
        );
    }

    public function test_dashboard_shows_pending_onboarding_users(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['user_id' => $user->id, 'community_id' => $community->id]);

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('pendingOnboarding.data', 1)
        );
    }

    // ── updateSettings ───────────────────────────────────────────────────────

    public function test_update_settings_saves_theme(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/settings', [
            'app_theme' => 'yellow',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Theme updated.');
        $this->assertDatabaseHas('settings', ['key' => 'app_theme', 'value' => 'yellow']);
    }

    public function test_update_settings_validates_theme(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/settings', [
            'app_theme' => 'blue',
        ]);

        $response->assertSessionHasErrors('app_theme');
    }

    public function test_update_settings_requires_theme(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/settings', []);

        $response->assertSessionHasErrors('app_theme');
    }

    // ── payouts ──────────────────────────────────────────────────────────────

    public function test_payouts_page_renders(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/payouts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts')
            ->has('owners')
            ->has('affiliates')
            ->has('stats')
            ->has('xenditBalance')
            ->has('payoutRequests')
        );
    }

    public function test_payouts_page_shows_affiliate_payout_requests_with_payout_details(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create([
            'payout_method' => 'maya',
            'payout_details' => '09179876543',
        ]);
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF-PAYOUT',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        PayoutRequest::create([
            'user_id' => $affiliateUser->id,
            'type' => PayoutRequest::TYPE_AFFILIATE,
            'community_id' => $community->id,
            'affiliate_id' => $affiliate->id,
            'amount' => 100,
            'eligible_amount' => 100,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payouts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts')
            ->has('payoutRequests', 1)
            ->where('payoutRequests.0.payout_method', 'maya')
            ->where('payoutRequests.0.payout_details', '09179876543')
        );
    }

    public function test_payouts_page_shows_owner_payout_request_with_user_payout_details(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create([
            'payout_method' => 'gcash',
            'payout_details' => '09170001111',
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        PayoutRequest::create([
            'user_id' => $owner->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 300,
            'eligible_amount' => 300,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payouts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts')
            ->has('payoutRequests', 1)
            ->where('payoutRequests.0.payout_method', 'gcash')
            ->where('payoutRequests.0.payout_details', '09170001111')
            ->where('payoutRequests.0.type', PayoutRequest::TYPE_OWNER)
        );
    }

    public function test_payouts_page_shows_owner_data(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 500,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->get('/admin/payouts');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Payouts')
            ->has('owners', 1)
        );
    }

    // ── payOwner ─────────────────────────────────────────────────────────────

    public function test_pay_owner_success(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payouts/owner/{$community->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('owner_payouts', [
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'status' => 'accepted',
        ]);
    }

    public function test_pay_owner_via_maya(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'maya', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payouts/owner/{$community->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_pay_owner_fails_without_payout_details(): void
    {
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $response = $this->actingAs($admin)->post("/admin/payouts/owner/{$community->id}");

        $response->assertStatus(422);
    }

    public function test_pay_owner_returns_error_when_no_pending_amount(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $response = $this->actingAs($admin)->post("/admin/payouts/owner/{$community->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'No pending amount for this community.');
    }

    public function test_pay_owner_handles_xendit_failure(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPayout')->andThrow(new \RuntimeException('Connection timeout'));
        });

        $admin = $this->superAdmin();
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payouts/owner/{$community->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('owner_payouts', ['community_id' => $community->id]);
    }

    // ── batchPayOwners ───────────────────────────────────────────────────────

    public function test_batch_pay_owners_success(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/batch');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('owner_payouts', ['community_id' => $community->id, 'status' => 'accepted']);
    }

    public function test_batch_pay_owners_skips_owners_without_payout_method(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/batch');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Paid 0 community owner(s).');
    }

    public function test_batch_pay_owners_reports_xendit_errors(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPayout')->andThrow(new \RuntimeException('API down'));
        });

        $admin = $this->superAdmin();
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/batch');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ── paySelectedOwners ────────────────────────────────────────────────────

    public function test_pay_selected_owners_success(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/selected', [
            'community_ids' => [$community->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('owner_payouts', ['community_id' => $community->id]);
    }

    public function test_pay_selected_owners_validates_community_ids(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/selected', []);

        $response->assertSessionHasErrors('community_ids');
    }

    public function test_pay_selected_owners_skips_owners_without_payout_method(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/selected', [
            'community_ids' => [$community->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Paid 0 selected owner(s).');
    }

    public function test_pay_selected_owners_reports_xendit_errors(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPayout')->andThrow(new \RuntimeException('Xendit API unavailable'));
        });

        $admin = $this->superAdmin();
        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500, 'name' => 'Test Community']);
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Payment::factory()->create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 1000,
            'status' => Payment::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post('/admin/payouts/owners/selected', [
            'community_ids' => [$community->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Test Community: Xendit API unavailable', session('error'));
        $this->assertDatabaseMissing('owner_payouts', ['community_id' => $community->id]);
    }

    // ── batchPayAffiliates ───────────────────────────────────────────────────

    private function createAffiliateConversion(Affiliate $affiliate, Community $community): AffiliateConversion
    {
        $subscriber = User::factory()->create();
        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        return AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'referred_user_id' => $subscriber->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);
    }

    public function test_batch_pay_affiliates_success(): void
    {
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['price' => 500]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF001',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $this->createAffiliateConversion($affiliate, $community);

        $disburseMock = Mockery::mock(DisbursePayout::class);
        $disburseMock->shouldReceive('execute')->once()->andReturn(['id' => 'payout_123']);
        $this->app->instance(DisbursePayout::class, $disburseMock);

        $markMock = Mockery::mock(MarkAffiliateConversionPaid::class);
        $markMock->shouldReceive('execute')->once();
        $this->app->instance(MarkAffiliateConversionPaid::class, $markMock);

        $response = $this->actingAs($admin)->post('/admin/payouts/affiliates/batch');

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_batch_pay_affiliates_with_no_pending_conversions(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/payouts/affiliates/batch');

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Paid 0 affiliate conversion(s).');
    }

    public function test_batch_pay_affiliates_handles_errors(): void
    {
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['price' => 500]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF002',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $this->createAffiliateConversion($affiliate, $community);

        $disburseMock = Mockery::mock(DisbursePayout::class);
        $disburseMock->shouldReceive('execute')->andThrow(new \RuntimeException('Payout error'));
        $this->app->instance(DisbursePayout::class, $disburseMock);

        $markMock = Mockery::mock(MarkAffiliateConversionPaid::class);
        $markMock->shouldReceive('execute')->never();
        $this->app->instance(MarkAffiliateConversionPaid::class, $markMock);

        $response = $this->actingAs($admin)->post('/admin/payouts/affiliates/batch');

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ── paySelectedAffiliates ────────────────────────────────────────────────

    public function test_pay_selected_affiliates_success(): void
    {
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['price' => 500]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF003',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $this->createAffiliateConversion($affiliate, $community);

        $disburseMock = Mockery::mock(DisbursePayout::class);
        $disburseMock->shouldReceive('execute')->once()->andReturn(['id' => 'payout_456']);
        $this->app->instance(DisbursePayout::class, $disburseMock);

        $markMock = Mockery::mock(MarkAffiliateConversionPaid::class);
        $markMock->shouldReceive('execute')->once();
        $this->app->instance(MarkAffiliateConversionPaid::class, $markMock);

        $response = $this->actingAs($admin)->post('/admin/payouts/affiliates/selected', [
            'affiliate_ids' => [$affiliate->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_pay_selected_affiliates_validates_affiliate_ids(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/payouts/affiliates/selected', []);

        $response->assertSessionHasErrors('affiliate_ids');
    }

    public function test_pay_selected_affiliates_handles_errors(): void
    {
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['price' => 500]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF004',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $this->createAffiliateConversion($affiliate, $community);

        $disburseMock = Mockery::mock(DisbursePayout::class);
        $disburseMock->shouldReceive('execute')->andThrow(new \RuntimeException('Network error'));
        $this->app->instance(DisbursePayout::class, $disburseMock);

        $markMock = Mockery::mock(MarkAffiliateConversionPaid::class);
        $markMock->shouldReceive('execute')->never();
        $this->app->instance(MarkAffiliateConversionPaid::class, $markMock);

        $response = $this->actingAs($admin)->post('/admin/payouts/affiliates/selected', [
            'affiliate_ids' => [$affiliate->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ── approvePayoutRequest ─────────────────────────────────────────────────

    public function test_approve_owner_payout_request_success(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $owner = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $owner->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('owner_payouts', [
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'status' => 'accepted',
        ]);
    }

    public function test_approve_affiliate_payout_request_success(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create([
            'payout_method' => 'maya',
            'payout_details' => '09171234567',
        ]);
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF005',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
        ]);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $affiliateUser->id,
            'type' => PayoutRequest::TYPE_AFFILIATE,
            'community_id' => $community->id,
            'affiliate_id' => $affiliate->id,
            'amount' => 100,
            'eligible_amount' => 100,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_APPROVED,
        ]);
        $this->assertDatabaseMissing('owner_payouts', ['community_id' => $community->id]);
    }

    public function test_approve_payout_request_fails_if_not_pending(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/approve");

        $response->assertStatus(422);
    }

    public function test_approve_payout_request_fails_without_payout_method(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        $user = User::factory()->create(['payout_method' => null, 'payout_details' => null]);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/approve");

        $response->assertStatus(422);
    }

    public function test_approve_payout_request_handles_xendit_failure(): void
    {
        $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createPayout')->andThrow(new \RuntimeException('Xendit down'));
        });

        $admin = $this->superAdmin();
        $user = User::factory()->create(['payout_method' => 'gcash', 'payout_details' => '09171234567']);
        $community = Community::factory()->create(['owner_id' => $user->id]);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);
    }

    // ── rejectPayoutRequest ──────────────────────────────────────────────────

    public function test_reject_payout_request_success(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/reject", [
            'reason' => 'Insufficient documentation',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => 'Insufficient documentation',
        ]);
    }

    public function test_reject_payout_request_without_reason(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/reject");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_REJECTED,
            'rejection_reason' => null,
        ]);
    }

    public function test_reject_payout_request_fails_if_not_pending(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_REJECTED,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/reject");

        $response->assertStatus(422);
    }

    // ── resendOnboardingEmail ────────────────────────────────────────────────

    public function test_resend_onboarding_email_success(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['user_id' => $user->id, 'community_id' => $community->id]);

        $response = $this->actingAs($admin)->post("/admin/onboarding/{$user->id}/resend");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        Mail::assertQueued(TempPasswordMail::class, function (TempPasswordMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_resend_onboarding_email_fails_if_user_already_set_password(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create(['needs_password_setup' => false]);

        $response = $this->actingAs($admin)->post("/admin/onboarding/{$user->id}/resend");

        $response->assertStatus(422);
    }

    public function test_resend_onboarding_email_fails_if_no_community(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create(['needs_password_setup' => true]);

        $response = $this->actingAs($admin)->post("/admin/onboarding/{$user->id}/resend");

        $response->assertStatus(422);
    }

    public function test_resend_onboarding_resets_password(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['user_id' => $user->id, 'community_id' => $community->id]);

        $oldPassword = $user->password;
        $this->actingAs($admin)->post("/admin/onboarding/{$user->id}/resend");

        $user->refresh();
        $this->assertNotEquals($oldPassword, $user->password);
    }

    // ── users ────────────────────────────────────────────────────────────────

    public function test_users_page_renders(): void
    {
        $admin = $this->superAdmin();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data')
            ->has('filters')
        );
    }

    public function test_users_page_filters_by_search(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $response = $this->actingAs($admin)->get('/admin/users?search=John');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users')
            ->has('users.data', 1)
            ->where('filters.search', 'John')
        );
    }

    public function test_users_page_search_by_email(): void
    {
        $admin = $this->superAdmin();
        User::factory()->create(['email' => 'specific@example.com']);
        User::factory()->create(['email' => 'other@example.com']);

        $response = $this->actingAs($admin)->get('/admin/users?search=specific');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
        );
    }

    // ── toggleUserStatus ─────────────────────────────────────────────────────

    public function test_toggle_user_status_disables_user(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create(['is_active' => true, 'is_super_admin' => false]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle-status");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
    }

    public function test_toggle_user_status_enables_user(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create(['is_active' => false, 'is_super_admin' => false]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle-status");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_toggle_user_status_rejects_super_admin(): void
    {
        $admin = $this->superAdmin();
        $otherAdmin = User::factory()->create(['is_super_admin' => true, 'is_active' => true]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$otherAdmin->id}/toggle-status");

        $response->assertStatus(422);
    }

    // ── trashedPosts ─────────────────────────────────────────────────────────

    public function test_trashed_posts_page_renders(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create();
        $post->delete();

        $response = $this->actingAs($admin)->get('/admin/posts/trashed');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/TrashedPosts')
            ->has('posts.data', 1)
            ->has('filters')
        );
    }

    public function test_trashed_posts_search_filter(): void
    {
        $admin = $this->superAdmin();

        $post1 = Post::factory()->create(['title' => 'Laravel Tips']);
        $post1->delete();

        $post2 = Post::factory()->create(['title' => 'Vue Guide']);
        $post2->delete();

        $response = $this->actingAs($admin)->get('/admin/posts/trashed?search=Laravel');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('posts.data', 1)
            ->where('filters.search', 'Laravel')
        );
    }

    public function test_trashed_posts_does_not_show_non_trashed(): void
    {
        $admin = $this->superAdmin();
        Post::factory()->create();

        $response = $this->actingAs($admin)->get('/admin/posts/trashed');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('posts.data', 0)
        );
    }

    // ── restorePost ──────────────────────────────────────────────────────────

    public function test_restore_post_success(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create();
        $post->delete();

        $response = $this->actingAs($admin)->post("/admin/posts/{$post->id}/restore");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Post restored.');
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
    }

    public function test_restore_post_fails_for_non_trashed(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create();

        $response = $this->actingAs($admin)->post("/admin/posts/{$post->id}/restore");

        $response->assertNotFound();
    }

    // ── forceDeletePost ──────────────────────────────────────────────────────

    public function test_force_delete_post_success(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create();
        $post->delete();

        $response = $this->actingAs($admin)->delete("/admin/posts/{$post->id}/force-delete");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Post permanently deleted.');
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_force_delete_post_fails_for_non_trashed(): void
    {
        $admin = $this->superAdmin();
        $post = Post::factory()->create();

        $response = $this->actingAs($admin)->delete("/admin/posts/{$post->id}/force-delete");

        $response->assertNotFound();
    }

    // ── emailTemplates ───────────────────────────────────────────────────────

    public function test_email_templates_page_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/email-templates');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/EmailTemplates')
            ->has('templates')
        );
    }

    public function test_email_templates_page_lists_seeded_templates(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/email-templates');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/EmailTemplates')
            ->has('templates', EmailTemplate::count())
        );
    }

    // ── editEmailTemplate ────────────────────────────────────────────────────

    public function test_edit_email_template_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/email-templates/welcome/edit');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/EmailTemplateEdit')
            ->has('template')
            ->where('template.key', 'welcome')
        );
    }

    public function test_edit_email_template_404_for_missing_key(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/email-templates/nonexistent/edit');

        $response->assertNotFound();
    }

    // ── updateEmailTemplate ──────────────────────────────────────────────────

    public function test_update_email_template_success(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->put('/admin/email-templates/welcome', [
            'subject' => 'New Subject',
            'html_body' => '<p>New Body</p>',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Email template saved.');
        $this->assertDatabaseHas('email_templates', [
            'key' => 'welcome',
            'subject' => 'New Subject',
            'html_body' => '<p>New Body</p>',
        ]);
    }

    public function test_update_email_template_validates_required_fields(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->put('/admin/email-templates/welcome', []);

        $response->assertSessionHasErrors(['subject', 'html_body']);
    }

    public function test_update_email_template_404_for_missing_key(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->put('/admin/email-templates/nonexistent', [
            'subject' => 'Subject',
            'html_body' => '<p>Body</p>',
        ]);

        $response->assertNotFound();
    }

    // ── previewEmailTemplate ─────────────────────────────────────────────────

    public function test_preview_email_template_returns_html(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/email-templates/welcome/preview', [
            'subject' => 'Preview Subject',
            'html_body' => '<p>Hello {{user_name}}, welcome to {{community_name}}!</p>',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('[user_name]', $response->getContent());
        $this->assertStringContainsString('[community_name]', $response->getContent());
    }

    public function test_preview_email_template_validates_fields(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/email-templates/welcome/preview', []);

        $response->assertSessionHasErrors(['subject', 'html_body']);
    }

    public function test_preview_email_template_404_for_missing_key(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/email-templates/nonexistent/preview', [
            'subject' => 'Subject',
            'html_body' => '<p>Body</p>',
        ]);

        $response->assertNotFound();
    }

    // ── updateCreatorPlanPricing ─────────────────────────────────────────────

    public function test_update_creator_plan_pricing_saves_prices(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-pricing', [
            'basic_price' => 299,
            'pro_price' => 999,
            'basic_annual_price' => 2990,
            'pro_annual_price' => 9990,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Creator plan pricing updated.');
        $this->assertDatabaseHas('settings', ['key' => 'creator_plan_basic_price', 'value' => '299']);
        $this->assertDatabaseHas('settings', ['key' => 'creator_plan_pro_price', 'value' => '999']);
        $this->assertDatabaseHas('settings', ['key' => 'creator_plan_basic_annual_price', 'value' => '2990']);
        $this->assertDatabaseHas('settings', ['key' => 'creator_plan_pro_annual_price', 'value' => '9990']);
    }

    public function test_update_creator_plan_pricing_validates_basic_price_required(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-pricing', [
            'pro_price' => 999,
        ]);

        $response->assertSessionHasErrors('basic_price');
    }

    public function test_update_creator_plan_pricing_validates_pro_price_required(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-pricing', [
            'basic_price' => 299,
        ]);

        $response->assertSessionHasErrors('pro_price');
    }

    public function test_update_creator_plan_pricing_rejects_negative_values(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-pricing', [
            'basic_price' => -10,
            'pro_price' => 999,
        ]);

        $response->assertSessionHasErrors('basic_price');
    }

    // ── updateCreatorPlanAffiliateSettings ───────────────────────────────────

    public function test_update_creator_plan_affiliate_settings_saves(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-affiliate-settings', [
            'commission_rate' => 25,
            'max_months' => 6,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Creator Plan affiliate settings updated.');
        $this->assertDatabaseHas('settings', ['key' => 'creator_plan_affiliate_commission_rate', 'value' => '25']);
        $this->assertDatabaseHas('settings', ['key' => 'creator_plan_affiliate_max_months', 'value' => '6']);
    }

    public function test_update_creator_plan_affiliate_settings_rejects_rate_over_100(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-affiliate-settings', [
            'commission_rate' => 150,
            'max_months' => 12,
        ]);

        $response->assertSessionHasErrors('commission_rate');
    }

    public function test_update_creator_plan_affiliate_settings_rejects_zero_max_months(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->patch('/admin/creator-plan-affiliate-settings', [
            'commission_rate' => 20,
            'max_months' => 0,
        ]);

        $response->assertSessionHasErrors('max_months');
    }

    public function test_update_creator_plan_affiliate_settings_requires_super_admin(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $response = $this->actingAs($user)->patch('/admin/creator-plan-affiliate-settings', [
            'commission_rate' => 20,
            'max_months' => 12,
        ]);

        $response->assertForbidden();
    }

    // ── toggleFeatured ──────────────────────────────────────────────────────

    public function test_toggle_featured_makes_community_featured(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'is_featured' => false]);

        $response = $this->actingAs($admin)->post("/admin/communities/{$community->slug}/toggle-featured");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'is_featured' => true]);
    }

    public function test_toggle_featured_makes_community_unfeatured(): void
    {
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'is_featured' => true]);

        $response = $this->actingAs($admin)->post("/admin/communities/{$community->slug}/toggle-featured");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'is_featured' => false]);
    }

    // ── creatorAnalytics ────────────────────────────────────────────────────

    public function test_creator_analytics_page_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/creator-analytics');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/CreatorAnalytics'));
    }

    public function test_creator_analytics_accepts_search_and_plan_filters(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/creator-analytics?search=test&plan=basic');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/CreatorAnalytics'));
    }

    // ── affiliateAnalytics ──────────────────────────────────────────────────

    public function test_affiliate_analytics_page_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/affiliate-analytics');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/AffiliateAnalytics'));
    }

    public function test_affiliate_analytics_accepts_search_and_status_filters(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/affiliate-analytics?search=test&status=active');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/AffiliateAnalytics'));
    }

    // ── markPayoutRequestPaid ───────────────────────────────────────────────

    public function test_mark_payout_request_paid_for_owner_type(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'community_id' => $community->id,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/mark-paid");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_PAID,
        ]);
    }

    public function test_mark_payout_request_paid_for_affiliate_type_settles_conversions(): void
    {
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create(['price' => 500]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF-MARK',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 50,
            'total_paid' => 0,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);
        $conversion = $this->createAffiliateConversion($affiliate, $community);

        $payoutRequest = PayoutRequest::create([
            'user_id' => $affiliateUser->id,
            'type' => PayoutRequest::TYPE_AFFILIATE,
            'community_id' => $community->id,
            'affiliate_id' => $affiliate->id,
            'amount' => 50,
            'eligible_amount' => 50,
            'status' => PayoutRequest::STATUS_APPROVED,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/mark-paid");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('payout_requests', [
            'id' => $payoutRequest->id,
            'status' => PayoutRequest::STATUS_PAID,
        ]);
    }

    public function test_mark_payout_request_paid_rejects_non_approved(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PENDING,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/mark-paid");

        $response->assertStatus(422);
    }

    public function test_mark_payout_request_paid_rejects_already_paid(): void
    {
        $admin = $this->superAdmin();

        $user = User::factory()->create();
        $payoutRequest = PayoutRequest::create([
            'user_id' => $user->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'amount' => 500,
            'eligible_amount' => 500,
            'status' => PayoutRequest::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)->post("/admin/payout-requests/{$payoutRequest->id}/mark-paid");

        $response->assertStatus(422);
    }

    // ── globalAnnouncement ──────────────────────────────────────────────────

    public function test_global_announcement_page_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/announcements');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/GlobalAnnouncement'));
    }

    public function test_send_global_announcement_success(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();

        // Create some active users to receive the announcement
        User::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'subject' => 'Test Announcement',
            'message' => 'This is a test announcement body.',
            'audience' => 'all',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_send_global_announcement_validates_required_fields(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/announcements', []);

        $response->assertSessionHasErrors(['subject', 'message', 'audience']);
    }

    public function test_send_global_announcement_validates_audience_values(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'subject' => 'Test',
            'message' => 'Body',
            'audience' => 'invalid_audience',
        ]);

        $response->assertSessionHasErrors('audience');
    }

    public function test_send_global_announcement_to_affiliates(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();

        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF-ANN',
            'status' => Affiliate::STATUS_ACTIVE,
            'payout_method' => 'gcash',
            'payout_details' => '09171234567',
        ]);

        $response = $this->actingAs($admin)->post('/admin/announcements', [
            'subject' => 'Affiliate News',
            'message' => 'News for affiliates.',
            'audience' => 'affiliates',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertStringContainsString('1 recipients', session('success'));
    }

    // ── dashboard includes creator plan pricing ─────────────────────────────

    public function test_dashboard_includes_creator_plan_pricing(): void
    {
        $this->mockXendit();
        $admin = $this->superAdmin();

        Setting::set('creator_plan_basic_price', '399');
        Setting::set('creator_plan_pro_price', '1599');

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('creatorPlanPricing')
            ->where('creatorPlanPricing.basic_price', 399)
            ->where('creatorPlanPricing.pro_price', 1599)
        );
    }

    // ── coupons ─────────────────────────────────────────────────────────────

    public function test_coupons_page_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/coupons');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Coupons')
            ->has('coupons')
        );
    }

    public function test_coupons_page_lists_existing_coupons(): void
    {
        $admin = $this->superAdmin();

        Coupon::create([
            'code' => 'TESTCODE1',
            'plan' => 'basic',
            'duration_months' => 3,
            'max_redemptions' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/coupons');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Coupons')
            ->has('coupons', 1)
            ->where('coupons.0.code', 'TESTCODE1')
        );
    }

    // ── storeCoupon ─────────────────────────────────────────────────────────

    public function test_store_coupon_success(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'SAVE50',
            'plan' => 'pro',
            'duration_months' => 6,
            'max_redemptions' => 100,
            'expires_at' => now()->addMonth()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE50',
            'plan' => 'pro',
            'duration_months' => 6,
            'max_redemptions' => 100,
        ]);
    }

    public function test_store_coupon_uppercases_code(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'lowercase',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'LOWERCASE']);
    }

    public function test_store_coupon_validates_required_fields(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', []);

        $response->assertSessionHasErrors(['code', 'plan', 'duration_months', 'max_redemptions']);
    }

    public function test_store_coupon_validates_unique_code(): void
    {
        $admin = $this->superAdmin();

        Coupon::create([
            'code' => 'DUPE',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 10,
        ]);

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'DUPE',
            'plan' => 'pro',
            'duration_months' => 3,
            'max_redemptions' => 5,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_store_coupon_validates_plan_values(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'BADPLAN',
            'plan' => 'enterprise',
            'duration_months' => 1,
            'max_redemptions' => 5,
        ]);

        $response->assertSessionHasErrors('plan');
    }

    public function test_store_coupon_rejects_past_expiry_date(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'EXPIRED',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 5,
            'expires_at' => now()->subDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('expires_at');
    }

    // ── discount-type coupons ────────────────────────────────────────────────

    public function test_store_discount_coupon_for_annual_success(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'LAUNCH30',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'annual',
            'discount_percent' => 30,
            'max_redemptions' => 100,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('coupons', [
            'code' => 'LAUNCH30',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'annual',
            'discount_percent' => 30,
        ]);
    }

    public function test_store_discount_coupon_for_annual_rejects_percent_below_baseline(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'WEAK',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'annual',
            'discount_percent' => 10, // < 16.67
            'max_redemptions' => 5,
        ]);

        $response->assertSessionHasErrors('discount_percent');
    }

    public function test_store_discount_coupon_for_both_cycles_rejects_percent_below_baseline(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'BOTHWEAK',
            'type' => 'discount',
            'plan' => 'both',
            'applies_to' => 'both',
            'discount_percent' => 15,
            'max_redemptions' => 5,
        ]);

        $response->assertSessionHasErrors('discount_percent');
    }

    public function test_store_monthly_discount_coupon_accepts_any_positive_percent(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'MONTH5',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'monthly',
            'discount_percent' => 5,
            'max_redemptions' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('coupons', ['code' => 'MONTH5', 'discount_percent' => 5]);
    }

    public function test_store_discount_coupon_rejects_duration_months(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'MIXED',
            'type' => 'discount',
            'plan' => 'pro',
            'applies_to' => 'annual',
            'discount_percent' => 25,
            'duration_months' => 6, // not allowed for discount type
            'max_redemptions' => 5,
        ]);

        $response->assertSessionHasErrors('duration_months');
    }

    public function test_store_plan_grant_coupon_rejects_plan_both(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->post('/admin/coupons', [
            'code' => 'BOTHGRANT',
            'plan' => 'both',
            'duration_months' => 1,
            'max_redemptions' => 5,
        ]);

        $response->assertSessionHasErrors('plan');
    }

    // ── toggleCoupon ────────────────────────────────────────────────────────

    public function test_toggle_coupon_deactivates(): void
    {
        $admin = $this->superAdmin();

        $coupon = Coupon::create([
            'code' => 'TOGGLE1',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post("/admin/coupons/{$coupon->id}/toggle");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'is_active' => false]);
    }

    public function test_toggle_coupon_activates(): void
    {
        $admin = $this->superAdmin();

        $coupon = Coupon::create([
            'code' => 'TOGGLE2',
            'plan' => 'pro',
            'duration_months' => 2,
            'max_redemptions' => 5,
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->post("/admin/coupons/{$coupon->id}/toggle");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'is_active' => true]);
    }

    // ── deleteCoupon ────────────────────────────────────────────────────────

    public function test_delete_coupon_success(): void
    {
        $admin = $this->superAdmin();

        $coupon = Coupon::create([
            'code' => 'DELETEME',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 10,
            'times_redeemed' => 0,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/coupons/{$coupon->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_delete_coupon_fails_if_already_redeemed(): void
    {
        $admin = $this->superAdmin();

        $coupon = Coupon::create([
            'code' => 'REDEEMED',
            'plan' => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 10,
            'times_redeemed' => 3,
        ]);

        $response = $this->actingAs($admin)->delete("/admin/coupons/{$coupon->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }

    // ── toggleKyc ───────────────────────────────────────────────────────────

    public function test_toggle_kyc_verifies_unverified_user(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'kyc_status' => User::KYC_NONE,
            'kyc_verified_at' => null,
        ]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle-kyc");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals(User::KYC_APPROVED, $user->kyc_status);
        $this->assertNotNull($user->kyc_verified_at);
    }

    public function test_toggle_kyc_unverifies_verified_user(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'kyc_status' => User::KYC_APPROVED,
            'kyc_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch("/admin/users/{$user->id}/toggle-kyc");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals(User::KYC_NONE, $user->kyc_status);
        $this->assertNull($user->kyc_verified_at);
    }

    // ── kycReviews ──────────────────────────────────────────────────────────

    public function test_kyc_reviews_page_renders(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->get('/admin/kyc-reviews');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/KycReviews')
            ->has('users')
            ->has('filters')
            ->has('counts')
        );
    }

    public function test_kyc_reviews_filters_by_status(): void
    {
        $admin = $this->superAdmin();

        User::factory()->create(['kyc_status' => User::KYC_SUBMITTED, 'kyc_submitted_at' => now()]);
        User::factory()->create(['kyc_status' => User::KYC_APPROVED, 'kyc_verified_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/kyc-reviews?status=approved');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/KycReviews')
            ->where('filters.status', 'approved')
            ->has('users.data', 1)
        );
    }

    public function test_kyc_reviews_returns_counts(): void
    {
        $admin = $this->superAdmin();

        User::factory()->count(2)->create(['kyc_status' => User::KYC_SUBMITTED, 'kyc_submitted_at' => now()]);
        User::factory()->create(['kyc_status' => User::KYC_APPROVED, 'kyc_verified_at' => now()]);

        $response = $this->actingAs($admin)->get('/admin/kyc-reviews');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('counts.submitted', 2)
            ->where('counts.approved', 1)
            ->where('counts.rejected', 0)
        );
    }

    // ── approveKyc ──────────────────────────────────────────────────────────

    public function test_approve_kyc_success(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch("/admin/kyc-reviews/{$user->id}/approve");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals(User::KYC_APPROVED, $user->kyc_status);
        $this->assertNotNull($user->kyc_verified_at);
        $this->assertNull($user->kyc_rejected_reason);
        Mail::assertQueued(\App\Mail\KycResultMail::class);
    }

    // ── rejectKyc ───────────────────────────────────────────────────────────

    public function test_reject_kyc_success(): void
    {
        Mail::fake();
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_submitted_at' => now(),
            'kyc_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch("/admin/kyc-reviews/{$user->id}/reject", [
            'reason' => 'Document is blurry',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $user->refresh();
        $this->assertEquals(User::KYC_REJECTED, $user->kyc_status);
        $this->assertNull($user->kyc_verified_at);
        $this->assertEquals('Document is blurry', $user->kyc_rejected_reason);
        Mail::assertQueued(\App\Mail\KycResultMail::class);
    }

    public function test_reject_kyc_requires_reason(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'kyc_status' => User::KYC_SUBMITTED,
            'kyc_submitted_at' => now(),
        ]);

        $response = $this->actingAs($admin)->patch("/admin/kyc-reviews/{$user->id}/reject", []);

        $response->assertSessionHasErrors('reason');
    }
}
