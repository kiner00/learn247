<?php

namespace Tests\Feature\Web;

use App\Actions\Affiliate\DisbursePayout;
use App\Actions\Affiliate\MarkAffiliateConversionPaid;
use App\Mail\TempPasswordMail;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailTemplate;
use App\Models\OwnerPayout;
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

        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id'   => $community->id,
            'user_id'        => $affiliateUser->id,
            'code'           => 'AFF-PAYOUT',
            'status'         => Affiliate::STATUS_ACTIVE,
            'payout_method'  => 'maya',
            'payout_details' => '09179876543',
        ]);
        PayoutRequest::create([
            'user_id'         => $affiliateUser->id,
            'type'            => PayoutRequest::TYPE_AFFILIATE,
            'community_id'    => $community->id,
            'affiliate_id'    => $affiliate->id,
            'amount'          => 100,
            'eligible_amount' => 100,
            'status'          => PayoutRequest::STATUS_PENDING,
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
            'payout_method'  => 'gcash',
            'payout_details' => '09170001111',
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        PayoutRequest::create([
            'user_id'         => $owner->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'community_id'    => $community->id,
            'amount'          => 300,
            'eligible_amount' => 300,
            'status'          => PayoutRequest::STATUS_PENDING,
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

        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'AFF005',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 100,
            'total_paid' => 0,
            'payout_method' => 'maya',
            'payout_details' => '09171234567',
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
        Mail::assertSent(TempPasswordMail::class, function (TempPasswordMail $mail) use ($user) {
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
}
