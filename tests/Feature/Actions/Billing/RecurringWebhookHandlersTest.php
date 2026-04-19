<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\HandleXenditWebhook;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CreatorSubscription;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecurringWebhookHandlersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);
    }

    private function makeRequest(array $body): Request
    {
        $request = Request::create('/xendit/webhook', 'POST', $body);
        $request->headers->set('x-callback-token', 'valid-token');

        return $request;
    }

    // ─── recurring.plan.activated ─────────────────────────────────────────────

    public function test_plan_activated_sets_subscription_active_with_expiry(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'xendit_plan_id' => 'repl_activate_001',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.plan.activated',
            'data' => ['id' => 'repl_activate_001'],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertEquals('ACTIVE', $subscription->recurring_status);
        $this->assertNotNull($subscription->expires_at);
        $this->assertTrue($subscription->expires_at->isFuture());
    }

    public function test_plan_activated_sets_creator_subscription_active(): void
    {
        $user = User::factory()->create();
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_BASIC,
            'status' => CreatorSubscription::STATUS_PENDING,
            'xendit_plan_id' => 'repl_creator_activate',
            'recurring_status' => 'REQUIRES_ACTION',
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.plan.activated',
            'data' => ['id' => 'repl_creator_activate'],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $creatorSub->refresh();
        $this->assertEquals(CreatorSubscription::STATUS_ACTIVE, $creatorSub->status);
        $this->assertEquals('ACTIVE', $creatorSub->recurring_status);
        $this->assertNotNull($creatorSub->expires_at);
    }

    // ─── recurring.plan.inactivated ───────────────────────────────────────────

    public function test_plan_inactivated_sets_inactive_but_keeps_access(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();
        $expiresAt = now()->addDays(20);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_inactivate_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => $expiresAt,
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.plan.inactivated',
            'data' => ['id' => 'repl_inactivate_001'],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $subscription->refresh();
        $this->assertEquals('INACTIVE', $subscription->recurring_status);
        // Status and expires_at should NOT change — access continues until period ends
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($expiresAt)) < 5,
            'Expiry should remain unchanged after inactivation'
        );
    }

    // ─── recurring.cycle.succeeded — Subscription ─────────────────────────────

    public function test_cycle_succeeded_extends_subscription_expiry(): void
    {
        Mail::fake();
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $originalExpiry = now()->addDays(5);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_cycle_sub_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => $originalExpiry,
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_cycle_sub_001',
                'cycle_id' => 'cycle_sub_001',
                'id' => 'cycle_sub_001',
                'amount' => 499,
                'currency' => 'PHP',
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $subscription->refresh();
        $expectedExpiry = $originalExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($subscription->expires_at->diffInSeconds($expectedExpiry)) < 5,
            'Expiry should be extended by 1 month from original'
        );
    }

    public function test_cycle_succeeded_creates_payment_record(): void
    {
        Mail::fake();
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_pay_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(3),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_pay_001',
                'cycle_id' => 'cycle_pay_001',
                'id' => 'cycle_pay_001',
                'amount' => 499,
                'currency' => 'PHP',
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
            'amount' => 499,
            'status' => Payment::STATUS_PAID,
            'xendit_event_id' => 'cycle_pay_001_SUCCEEDED',
        ]);
    }

    public function test_cycle_succeeded_idempotency_prevents_duplicate(): void
    {
        Mail::fake();
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_idemp_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(3),
        ]);

        // Pre-create the payment record (simulating already processed)
        Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $user->id,
            'amount' => 499,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'xendit_event_id' => 'cycle_idemp_001_SUCCEEDED',
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_idemp_001',
                'cycle_id' => 'cycle_idemp_001',
                'id' => 'cycle_idemp_001',
                'amount' => 499,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertEquals(1, Payment::where('xendit_event_id', 'cycle_idemp_001_SUCCEEDED')->count());
    }

    public function test_cycle_succeeded_creates_affiliate_for_subscriber(): void
    {
        Mail::fake();
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_aff_auto_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(3),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_aff_auto_001',
                'cycle_id' => 'cycle_aff_auto_001',
                'id' => 'cycle_aff_auto_001',
                'amount' => 499,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }

    public function test_cycle_succeeded_syncs_community_membership(): void
    {
        Mail::fake();
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_member_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(3),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_member_001',
                'cycle_id' => 'cycle_member_001',
                'id' => 'cycle_member_001',
                'amount' => 499,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_cycle_succeeded_records_affiliate_commission(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'affiliate_commission_rate' => 10,
        ]);

        $affiliateUser = User::factory()->create(['needs_password_setup' => false]);
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'REC_AFF_001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        // Affiliate must be subscribed
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $referredUser = User::factory()->create(['needs_password_setup' => false]);
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_aff_comm_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(3),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_aff_comm_001',
                'cycle_id' => 'cycle_aff_comm_001',
                'id' => 'cycle_aff_comm_001',
                'amount' => 499,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $subscription->id,
            'referred_user_id' => $referredUser->id,
        ]);
    }

    // ─── recurring.cycle.succeeded — Creator Plan ─────────────────────────────

    public function test_cycle_succeeded_extends_creator_plan_expiry(): void
    {
        $user = User::factory()->create();
        $originalExpiry = now()->addDays(5);
        $creatorSub = CreatorSubscription::create([
            'user_id' => $user->id,
            'plan' => CreatorSubscription::PLAN_PRO,
            'status' => CreatorSubscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_creator_cycle',
            'recurring_status' => 'ACTIVE',
            'expires_at' => $originalExpiry,
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_creator_cycle',
                'cycle_id' => 'cycle_creator_001',
                'id' => 'cycle_creator_001',
                'amount' => 1999,
                'currency' => 'PHP',
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $creatorSub->refresh();
        $expectedExpiry = $originalExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($creatorSub->expires_at->diffInSeconds($expectedExpiry)) < 5,
        );

        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'amount' => 1999,
            'status' => Payment::STATUS_PAID,
            'xendit_event_id' => 'cycle_creator_001_SUCCEEDED',
        ]);
    }

    // ─── recurring.cycle.succeeded — Course Enrollment ────────────────────────

    public function test_cycle_succeeded_extends_course_enrollment_expiry(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Monthly Course',
            'access_type' => Course::ACCESS_PAID_MONTHLY,
            'price' => 200,
            'position' => 1,
        ]);

        $originalExpiry = now()->addDays(5);
        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => CourseEnrollment::STATUS_PAID,
            'xendit_plan_id' => 'repl_course_cycle',
            'recurring_status' => 'ACTIVE',
            'expires_at' => $originalExpiry,
            'paid_at' => now(),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_course_cycle',
                'cycle_id' => 'cycle_course_001',
                'id' => 'cycle_course_001',
                'amount' => 200,
                'currency' => 'PHP',
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $enrollment->refresh();
        $expectedExpiry = $originalExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($enrollment->expires_at->diffInSeconds($expectedExpiry)) < 5,
        );
    }

    // ─── recurring.cycle.succeeded — Curzzo Purchase ──────────────────────────

    public function test_cycle_succeeded_extends_curzzo_purchase_expiry(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name' => 'Monthly Bot',
            'instructions' => 'Test bot',
            'billing_type' => 'monthly',
            'price' => 299,
            'is_active' => true,
        ]);

        $originalExpiry = now()->addDays(5);
        $purchase = CurzzoPurchase::create([
            'user_id' => $user->id,
            'curzzo_id' => $curzzo->id,
            'status' => CurzzoPurchase::STATUS_PAID,
            'xendit_plan_id' => 'repl_curzzo_cycle',
            'recurring_status' => 'ACTIVE',
            'expires_at' => $originalExpiry,
            'paid_at' => now(),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_curzzo_cycle',
                'cycle_id' => 'cycle_curzzo_001',
                'id' => 'cycle_curzzo_001',
                'amount' => 299,
                'currency' => 'PHP',
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $purchase->refresh();
        $expectedExpiry = $originalExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($purchase->expires_at->diffInSeconds($expectedExpiry)) < 5,
        );
    }

    // ─── routing: recurring events ────────────────────────────────────────────

    public function test_recurring_event_without_plan_id_is_logged_and_skipped(): void
    {
        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => ['amount' => 100], // no plan_id or id
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertEquals(0, Payment::count());
    }

    public function test_recurring_event_with_no_matching_plan_is_skipped(): void
    {
        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_nonexistent',
                'cycle_id' => 'cycle_nonexistent',
                'id' => 'cycle_nonexistent',
                'amount' => 100,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $this->assertEquals(0, Payment::count());
    }

    public function test_recurring_cycle_retrying_event_is_ignored(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_retry_001',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(3),
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.retrying',
            'data' => [
                'plan_id' => 'repl_retry_001',
                'cycle_id' => 'cycle_retry_001',
                'id' => 'cycle_retry_001',
                'amount' => 499,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        // retrying events should be ignored — no payment created
        $this->assertEquals(0, Payment::count());
    }

    // ─── expired subscription gets extended from now ──────────────────────────

    public function test_cycle_succeeded_extends_from_now_when_expiry_is_past(): void
    {
        Mail::fake();
        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_past_exp',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->subDay(), // already expired
        ]);

        $request = $this->makeRequest([
            'event' => 'recurring.cycle.succeeded',
            'data' => [
                'plan_id' => 'repl_past_exp',
                'cycle_id' => 'cycle_past_exp',
                'id' => 'cycle_past_exp',
                'amount' => 499,
            ],
        ]);

        app(HandleXenditWebhook::class)->execute($request);

        $subscription->refresh();
        // Should extend from now, not from the past expiry
        $this->assertTrue($subscription->expires_at->isFuture());
        $this->assertTrue(
            abs($subscription->expires_at->diffInDays(now()->addMonth())) < 2,
        );
    }
}
