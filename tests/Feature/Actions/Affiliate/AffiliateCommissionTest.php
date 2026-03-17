<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\HandleXenditWebhook;
use App\Actions\Classroom\EnrollInCourse;
use App\Mail\AffiliateChaChing;
use App\Mail\CreatorChaChing;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Covers the full affiliate commission lifecycle:
 *
 *   Subscription commissions (community rate)
 *   Course commissions (per-course rate set by creator)
 *   Active-membership gate: missed payouts and resumed earning
 *   Commission maths (platform fee, commission, creator share)
 *   EnrollInCourse affiliate capture
 *   End-to-end via Xendit webhook
 */
class AffiliateCommissionTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function community(array $attrs = []): Community
    {
        $owner = User::factory()->create();
        return Community::factory()->create(array_merge(['owner_id' => $owner->id], $attrs));
    }

    private function affiliate(Community $community, User $user, string $code = 'CODE1'): Affiliate
    {
        return Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'code'         => $code,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }

    private function subscription(Community $community, User $user, array $attrs = []): Subscription
    {
        return Subscription::create(array_merge([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_' . uniqid(),
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ], $attrs));
    }

    private function course(Community $community, array $attrs = []): Course
    {
        return Course::create(array_merge([
            'community_id'              => $community->id,
            'title'                     => 'Test Course',
            'access_type'               => Course::ACCESS_PAID_ONCE,
            'price'                     => 1000,
            'affiliate_commission_rate' => 30,
            'position'                  => 1,
        ], $attrs));
    }

    private function enrollment(Course $course, User $user, ?Affiliate $affiliate = null, array $attrs = []): CourseEnrollment
    {
        return CourseEnrollment::create(array_merge([
            'user_id'      => $user->id,
            'course_id'    => $course->id,
            'affiliate_id' => $affiliate?->id,
            'xendit_id'    => 'inv_' . uniqid(),
            'status'       => CourseEnrollment::STATUS_PENDING,
        ], $attrs));
    }

    private function payment(Subscription $subscription, float $amount = 1000): Payment
    {
        return Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $subscription->community_id,
            'user_id'         => $subscription->user_id,
            'amount'          => $amount,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'xendit_event_id' => 'evt_' . uniqid(),
            'metadata'        => [],
            'paid_at'         => now(),
        ]);
    }

    private function webhookRequest(array $body): Request
    {
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);
        $request = Request::create('/xendit/webhook', 'POST', $body);
        $request->headers->set('x-callback-token', 'valid-token');
        return $request;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION 1 — Subscription commissions
    // ══════════════════════════════════════════════════════════════════════════

    /** User A is active → B subscribes → A earns subscription commission */
    public function test_subscription_commission_recorded_when_affiliate_is_active(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 20]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);

        // A is active member
        $this->subscription($community, $affiliateUser);

        $referredUser = User::factory()->create();
        $sub          = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id, 'status' => Subscription::STATUS_ACTIVE]);
        $payment      = $this->payment($sub, 1000);

        $action = app(RecordAffiliateConversion::class);
        $action->execute($sub->load('affiliate.community'), $payment);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'     => $affiliate->id,
            'subscription_id'  => $sub->id,
            'referred_user_id' => $referredUser->id,
            'sale_amount'      => 1000,
            'commission_amount'=> 200.00,  // 20% of 1000
            'platform_fee'     => 150.00,  // 15% of 1000
            'creator_amount'   => 650.00,  // 1000 - 150 - 200
        ]);
    }

    /** User A's membership expired → B's subscription payment → A earns nothing */
    public function test_subscription_commission_skipped_when_affiliate_membership_expired(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 20]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);

        // A's membership expired
        $this->subscription($community, $affiliateUser, [
            'status'     => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $referredUser = User::factory()->create();
        $sub          = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id]);
        $payment      = $this->payment($sub, 1000);

        $action = app(RecordAffiliateConversion::class);
        $action->execute($sub->load('affiliate.community'), $payment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
        $this->assertEquals(0, $affiliate->fresh()->total_earned);
    }

    /** User A has a lifetime (one-time billing, null expires_at) membership → A still earns */
    public function test_subscription_commission_earned_with_lifetime_membership(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 10]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);

        // A has one-time/lifetime membership (null expires_at)
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $referredUser = User::factory()->create();
        $sub          = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id]);
        $payment      = $this->payment($sub, 500);

        $action = app(RecordAffiliateConversion::class);
        $action->execute($sub->load('affiliate.community'), $payment);

        $this->assertDatabaseCount('affiliate_conversions', 1);
        $this->assertEquals(50.00, (float) $affiliate->fresh()->total_earned); // 10% of 500
    }

    /** Affiliate has no subscription at all → no commission */
    public function test_subscription_commission_skipped_when_affiliate_has_no_subscription(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 20]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        // No subscription for affiliateUser

        $referredUser = User::factory()->create();
        $sub          = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id]);
        $payment      = $this->payment($sub, 1000);

        $action = app(RecordAffiliateConversion::class);
        $action->execute($sub->load('affiliate.community'), $payment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** Commission maths: 15% platform fee, rate% to affiliate, rest to creator */
    public function test_subscription_commission_math_is_correct(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 30]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $referredUser = User::factory()->create();
        $sub          = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id]);
        $payment      = $this->payment($sub, 1000);

        app(RecordAffiliateConversion::class)->execute($sub->load('affiliate.community'), $payment);

        $conversion = AffiliateConversion::first();
        $this->assertEquals(1000.00, (float) $conversion->sale_amount);
        $this->assertEquals(150.00,  (float) $conversion->platform_fee);      // 15%
        $this->assertEquals(300.00,  (float) $conversion->commission_amount); // 30%
        $this->assertEquals(550.00,  (float) $conversion->creator_amount);    // 55%
        $this->assertEquals(
            (float) $conversion->sale_amount,
            round((float) $conversion->platform_fee + (float) $conversion->commission_amount + (float) $conversion->creator_amount, 2),
            'platform_fee + commission + creator must equal sale_amount'
        );
    }

    /** total_earned on affiliate increments with each conversion */
    public function test_subscription_total_earned_increments(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 10]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        foreach (['ref1', 'ref2', 'ref3'] as $i => $xenditId) {
            $referredUser = User::factory()->create();
            $sub          = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id]);
            $payment      = $this->payment($sub, 1000);
            app(RecordAffiliateConversion::class)->execute($sub->load('affiliate.community'), $payment);
        }

        $this->assertEquals(300.00, (float) $affiliate->fresh()->total_earned); // 3 × 100
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION 2 — Course commissions
    // ══════════════════════════════════════════════════════════════════════════

    /** User A active → B buys paid course → A earns course commission rate */
    public function test_course_commission_recorded_when_affiliate_is_active(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser); // A is active

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['price' => 1000, 'affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'          => $affiliate->id,
            'course_enrollment_id'  => $enrollment->id,
            'referred_user_id'      => $buyer->id,
            'sale_amount'           => 1000,
            'commission_amount'     => 300.00,  // 30% of 1000
            'platform_fee'          => 150.00,  // 15% of 1000
            'creator_amount'        => 550.00,  // 1000 - 150 - 300
        ]);

        $this->assertEquals(300.00, (float) $affiliate->fresh()->total_earned);
    }

    /** User A's membership expired → B buys course → A earns nothing */
    public function test_course_commission_skipped_when_affiliate_membership_expired(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, [
            'status'     => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
        $this->assertEquals(0, (float) $affiliate->fresh()->total_earned);
    }

    /** User A has cancelled membership → B buys course → A earns nothing */
    public function test_course_commission_skipped_when_affiliate_membership_cancelled(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['status' => Subscription::STATUS_CANCELLED]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** User A has no subscription at all → B buys course → A earns nothing */
    public function test_course_commission_skipped_when_affiliate_has_no_subscription(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        // no subscription for A

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** User A has lifetime membership (null expires_at) → B buys course → A earns */
    public function test_course_commission_earned_with_lifetime_membership(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['price' => 500, 'affiliate_commission_rate' => 20]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseCount('affiliate_conversions', 1);
        $this->assertEquals(100.00, (float) $affiliate->fresh()->total_earned); // 20% of 500
    }

    /** Course has no commission rate set (null) → no commission recorded */
    public function test_course_commission_skipped_when_rate_is_null(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => null]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** Course commission rate is 0% → no commission recorded */
    public function test_course_commission_skipped_when_rate_is_zero(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 0]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** Enrollment has no affiliate_id → executeForCourse returns null, no conversion */
    public function test_course_commission_skipped_when_enrollment_has_no_affiliate(): void
    {
        $community  = $this->community();
        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, null); // no affiliate
        $enrollment->load(['affiliate', 'course']);

        $result = app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertNull($result);
        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** Course commission maths: 15% platform, rate% affiliate, rest to creator */
    public function test_course_commission_math_is_correct(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['price' => 1000, 'affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $conversion = AffiliateConversion::first();
        $this->assertEquals(1000.00, (float) $conversion->sale_amount);
        $this->assertEquals(150.00,  (float) $conversion->platform_fee);      // 15%
        $this->assertEquals(300.00,  (float) $conversion->commission_amount); // 30%
        $this->assertEquals(550.00,  (float) $conversion->creator_amount);    // 55%
        $this->assertEquals(
            (float) $conversion->sale_amount,
            round((float) $conversion->platform_fee + (float) $conversion->commission_amount + (float) $conversion->creator_amount, 2),
            'Parts must sum to sale_amount'
        );
    }

    /** executeForCourse conversion is linked to course_enrollment_id, not subscription_id */
    public function test_course_conversion_is_linked_to_enrollment_not_subscription(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);

        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $conversion = AffiliateConversion::first();
        $this->assertEquals($enrollment->id, $conversion->course_enrollment_id);
        $this->assertNull($conversion->subscription_id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION 3 — The "missed month / resumed earning" lifecycle
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Month 1: A active → B subscribes → A earns
     * Month 2: A lapses → B pays again → A misses payout
     * Month 3: A re-subscribes → B pays again → A earns again
     */
    public function test_affiliate_misses_payout_when_lapsed_then_resumes_when_reactivated_subscription(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 10]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);

        $referredUser = User::factory()->create();

        // ── Month 1: A is active ──────────────────────────────────────────────
        $affSub = $this->subscription($community, $affiliateUser, ['expires_at' => now()->addMonth()]);
        $sub    = $this->subscription($community, $referredUser, ['affiliate_id' => $affiliate->id]);
        app(RecordAffiliateConversion::class)->execute($sub->load('affiliate.community'), $this->payment($sub, 1000));
        $this->assertEquals(100.00, (float) $affiliate->fresh()->total_earned, 'Month 1: should earn');

        // ── Month 2: A lapses ─────────────────────────────────────────────────
        $affSub->update(['status' => Subscription::STATUS_EXPIRED, 'expires_at' => now()->subSecond()]);
        app(RecordAffiliateConversion::class)->execute($sub->load('affiliate.community'), $this->payment($sub, 1000));
        $this->assertEquals(100.00, (float) $affiliate->fresh()->total_earned, 'Month 2: should NOT earn (lapsed)');
        $this->assertEquals(1, AffiliateConversion::count(), 'Only one conversion should be recorded');

        // ── Month 3: A re-subscribes ──────────────────────────────────────────
        $affSub->update(['status' => Subscription::STATUS_ACTIVE, 'expires_at' => now()->addMonth()]);
        app(RecordAffiliateConversion::class)->execute($sub->load('affiliate.community'), $this->payment($sub, 1000));
        $this->assertEquals(200.00, (float) $affiliate->fresh()->total_earned, 'Month 3: should earn again');
        $this->assertEquals(2, AffiliateConversion::count(), 'Two conversions total (month 1 and 3)');
    }

    /**
     * Same lifecycle via course commissions:
     * Month 1: A active → B buys course → A earns
     * Month 2: A lapses → B buys another course → A misses
     * Month 3: A re-subscribes → B buys another course → A earns
     */
    public function test_affiliate_misses_course_payout_when_lapsed_then_resumes(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $buyer         = User::factory()->create();

        $courseA = $this->course($community, ['title' => 'Course A', 'affiliate_commission_rate' => 20]);
        $courseB = $this->course($community, ['title' => 'Course B', 'affiliate_commission_rate' => 20, 'position' => 2]);
        $courseC = $this->course($community, ['title' => 'Course C', 'affiliate_commission_rate' => 20, 'position' => 3]);

        // ── Month 1: A active ──────────────────────────────────────────────────
        $affSub = $this->subscription($community, $affiliateUser, ['expires_at' => now()->addMonth()]);

        $enrollA = $this->enrollment($courseA, $buyer, $affiliate);
        $enrollA->load(['affiliate', 'course']);
        app(RecordAffiliateConversion::class)->executeForCourse($enrollA);
        $this->assertEquals(200.00, (float) $affiliate->fresh()->total_earned, 'Month 1: earns on course A');

        // ── Month 2: A lapses ─────────────────────────────────────────────────
        $affSub->update(['status' => Subscription::STATUS_EXPIRED, 'expires_at' => now()->subSecond()]);

        $enrollB = $this->enrollment($courseB, $buyer, $affiliate);
        $enrollB->load(['affiliate', 'course']);
        app(RecordAffiliateConversion::class)->executeForCourse($enrollB);
        $this->assertEquals(200.00, (float) $affiliate->fresh()->total_earned, 'Month 2: misses course B payout');

        // ── Month 3: A re-subscribes ──────────────────────────────────────────
        $affSub->update(['status' => Subscription::STATUS_ACTIVE, 'expires_at' => now()->addMonth()]);

        $enrollC = $this->enrollment($courseC, $buyer, $affiliate);
        $enrollC->load(['affiliate', 'course']);
        app(RecordAffiliateConversion::class)->executeForCourse($enrollC);
        $this->assertEquals(400.00, (float) $affiliate->fresh()->total_earned, 'Month 3: earns on course C');

        $this->assertEquals(2, AffiliateConversion::count(), '2 conversions: month 1 and month 3 only');
    }

    /** A earns on both a subscription AND a course bought by the same referred user */
    public function test_affiliate_earns_on_both_subscription_and_course(): void
    {
        $community     = $this->community(['affiliate_commission_rate' => 50]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer = User::factory()->create();

        // Subscription commission: 50% of ₱1000 = ₱500
        $sub     = $this->subscription($community, $buyer, ['affiliate_id' => $affiliate->id]);
        $payment = $this->payment($sub, 1000);
        app(RecordAffiliateConversion::class)->execute($sub->load('affiliate.community'), $payment);

        // Course commission: 30% of ₱1000 = ₱300
        $course     = $this->course($community, ['price' => 1000, 'affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate);
        $enrollment->load(['affiliate', 'course']);
        app(RecordAffiliateConversion::class)->executeForCourse($enrollment);

        $this->assertEquals(800.00, (float) $affiliate->fresh()->total_earned, '₱500 (sub) + ₱300 (course) = ₱800');
        $this->assertEquals(2, AffiliateConversion::count());
    }

    /** A earns on multiple different courses bought by the same referred user */
    public function test_affiliate_earns_on_multiple_courses_from_same_buyer(): void
    {
        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer = User::factory()->create();

        foreach (range(1, 3) as $i) {
            $course     = $this->course($community, [
                'title'                     => "Course {$i}",
                'price'                     => 1000,
                'affiliate_commission_rate' => 10,
                'position'                  => $i,
            ]);
            $enrollment = $this->enrollment($course, $buyer, $affiliate);
            $enrollment->load(['affiliate', 'course']);
            app(RecordAffiliateConversion::class)->executeForCourse($enrollment);
        }

        // 3 × (10% of 1000) = ₱300
        $this->assertEquals(300.00, (float) $affiliate->fresh()->total_earned);
        $this->assertEquals(3, AffiliateConversion::count());
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION 4 — EnrollInCourse: affiliate capture
    // ══════════════════════════════════════════════════════════════════════════

    /** When B has a subscription via A's referral, enrollment captures affiliate_id */
    public function test_enroll_captures_affiliate_id_from_subscription(): void
    {
        Mail::fake();
        config(['services.xendit.secret_key' => 'test']);

        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);

        $buyer = User::factory()->create();
        $this->subscription($community, $buyer, ['affiliate_id' => $affiliate->id]);

        $course = $this->course($community, ['price' => 1000]);

        // Mock Xendit so we don't make real HTTP calls
        $mockXendit = $this->createMock(\App\Services\XenditService::class);
        $mockXendit->method('createInvoice')->willReturn([
            'id'          => 'inv_test_capture',
            'invoice_url' => 'https://checkout.xendit.co/test',
        ]);
        $this->app->instance(\App\Services\XenditService::class, $mockXendit);

        $action = app(EnrollInCourse::class);
        $result = $action->execute(
            $buyer,
            $community,
            $course,
            route('communities.classroom.courses.show', [$community->slug, $course->id])
        );

        $this->assertEquals($affiliate->id, $result['enrollment']->affiliate_id);
        $this->assertDatabaseHas('course_enrollments', [
            'user_id'      => $buyer->id,
            'course_id'    => $course->id,
            'affiliate_id' => $affiliate->id,
        ]);
    }

    /** When B has no subscription (guest buyer), enrollment has null affiliate_id */
    public function test_enroll_sets_null_affiliate_id_when_buyer_has_no_subscription(): void
    {
        Mail::fake();
        config(['services.xendit.secret_key' => 'test']);

        $community = $this->community();
        $buyer     = User::factory()->create();
        $course    = $this->course($community);

        $mockXendit = $this->createMock(\App\Services\XenditService::class);
        $mockXendit->method('createInvoice')->willReturn([
            'id'          => 'inv_test_no_aff',
            'invoice_url' => 'https://checkout.xendit.co/test',
        ]);
        $this->app->instance(\App\Services\XenditService::class, $mockXendit);

        $action = app(EnrollInCourse::class);
        $result = $action->execute(
            $buyer,
            $community,
            $course,
            route('communities.classroom.courses.show', [$community->slug, $course->id])
        );

        $this->assertNull($result['enrollment']->affiliate_id);
    }

    /** When B has a subscription but it has no affiliate_id, enrollment has null affiliate_id */
    public function test_enroll_sets_null_affiliate_id_when_subscription_has_no_affiliate(): void
    {
        Mail::fake();
        config(['services.xendit.secret_key' => 'test']);

        $community = $this->community();
        $buyer     = User::factory()->create();
        $this->subscription($community, $buyer, ['affiliate_id' => null]);
        $course = $this->course($community);

        $mockXendit = $this->createMock(\App\Services\XenditService::class);
        $mockXendit->method('createInvoice')->willReturn([
            'id'          => 'inv_test_sub_no_aff',
            'invoice_url' => 'https://checkout.xendit.co/test',
        ]);
        $this->app->instance(\App\Services\XenditService::class, $mockXendit);

        $action = app(EnrollInCourse::class);
        $result = $action->execute(
            $buyer,
            $community,
            $course,
            route('communities.classroom.courses.show', [$community->slug, $course->id])
        );

        $this->assertNull($result['enrollment']->affiliate_id);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SECTION 5 — End-to-end via Xendit webhook
    // ══════════════════════════════════════════════════════════════════════════

    /** Full flow: webhook fires for course enrollment → commission recorded */
    public function test_webhook_records_course_commission_when_enrollment_has_affiliate(): void
    {
        Mail::fake();

        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['price' => 1000, 'affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate, ['xendit_id' => 'inv_wh_course_1']);

        $request = $this->webhookRequest(['id' => 'inv_wh_course_1', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseHas('course_enrollments', [
            'id'     => $enrollment->id,
            'status' => CourseEnrollment::STATUS_PAID,
        ]);
        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'         => $affiliate->id,
            'course_enrollment_id' => $enrollment->id,
            'commission_amount'    => 300.00,
        ]);
    }

    /** Webhook: course enrollment paid, affiliate lapsed → no commission recorded */
    public function test_webhook_skips_course_commission_when_affiliate_lapsed(): void
    {
        Mail::fake();

        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, [
            'status'     => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate, ['xendit_id' => 'inv_wh_lapsed']);

        $request = $this->webhookRequest(['id' => 'inv_wh_lapsed', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseHas('course_enrollments', ['id' => $enrollment->id, 'status' => CourseEnrollment::STATUS_PAID]);
        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** Webhook: course enrollment paid, no affiliate on enrollment → no commission */
    public function test_webhook_skips_course_commission_when_no_affiliate_on_enrollment(): void
    {
        Mail::fake();

        $community  = $this->community();
        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, null, ['xendit_id' => 'inv_wh_noaff']);

        $request = $this->webhookRequest(['id' => 'inv_wh_noaff', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    /** Webhook: cha-ching emails sent to affiliate and creator on course purchase */
    public function test_webhook_sends_cha_ching_emails_on_course_commission(): void
    {
        Mail::fake();

        $owner         = User::factory()->create();
        $community     = Community::factory()->create(['owner_id' => $owner->id]);
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, ['expires_at' => null]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['price' => 1000, 'affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate, ['xendit_id' => 'inv_wh_email']);

        $request = $this->webhookRequest(['id' => 'inv_wh_email', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        Mail::assertQueued(AffiliateChaChing::class, fn ($m) => $m->hasTo($affiliateUser->email));
        Mail::assertQueued(CreatorChaChing::class,   fn ($m) => $m->hasTo($owner->email));
    }

    /** Webhook: no cha-ching emails sent when affiliate lapsed and no commission */
    public function test_webhook_does_not_send_cha_ching_emails_when_no_commission(): void
    {
        Mail::fake();

        $community     = $this->community();
        $affiliateUser = User::factory()->create();
        $affiliate     = $this->affiliate($community, $affiliateUser);
        $this->subscription($community, $affiliateUser, [
            'status'     => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);

        $buyer      = User::factory()->create();
        $course     = $this->course($community, ['affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $buyer, $affiliate, ['xendit_id' => 'inv_wh_no_email']);

        $request = $this->webhookRequest(['id' => 'inv_wh_no_email', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        Mail::assertNotQueued(AffiliateChaChing::class);
    }

    /**
     * Full scenario from the business spec:
     * - User A invites User B (50% community commission)
     * - B subscribes → A earns ₱500 (50% of ₱1000)
     * - B buys paid course → A earns ₱300 (30% of ₱1000)
     * - Total A earned = ₱800
     */
    public function test_full_business_scenario_a_earns_on_subscription_and_course(): void
    {
        Mail::fake();

        $owner         = User::factory()->create();
        $community     = Community::factory()->create([
            'owner_id'                  => $owner->id,
            'affiliate_commission_rate' => 50,
        ]);

        $userA = User::factory()->create();
        $userB = User::factory()->create(['needs_password_setup' => false]);

        // A is affiliate and has an active membership (lifetime)
        $affiliate = $this->affiliate($community, $userA, 'USERA_CODE');
        $this->subscription($community, $userA, ['expires_at' => null]);

        // ── Step 1: B subscribes via A's link ─────────────────────────────────
        $bSub = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $userB->id,
            'xendit_id'    => 'inv_bsub_1',
            'affiliate_id' => $affiliate->id,
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->webhookRequest(['id' => 'inv_bsub_1', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        $this->assertEquals(500.00, (float) $affiliate->fresh()->total_earned, 'A earns ₱500 from B subscription (50%)');

        // ── Step 2: B buys the paid course ────────────────────────────────────
        $course     = $this->course($community, ['price' => 1000, 'affiliate_commission_rate' => 30]);
        $enrollment = $this->enrollment($course, $userB, $affiliate, ['xendit_id' => 'inv_bcourse_1']);

        $request = $this->webhookRequest(['id' => 'inv_bcourse_1', 'status' => 'PAID', 'amount' => 1000]);
        app(HandleXenditWebhook::class)->execute($request);

        $this->assertEquals(800.00, (float) $affiliate->fresh()->total_earned, 'A total = ₱500 + ₱300 = ₱800');
        $this->assertEquals(2, AffiliateConversion::count(), 'Two conversions: subscription + course');

        Mail::assertQueued(AffiliateChaChing::class, 2); // once for sub, once for course
    }
}
