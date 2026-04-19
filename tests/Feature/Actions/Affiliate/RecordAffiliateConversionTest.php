<?php

namespace Tests\Feature\Actions\Affiliate;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CertificationPurchase;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseCertification;
use App\Models\CourseEnrollment;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RecordAffiliateConversionTest extends TestCase
{
    use RefreshDatabase;

    private RecordAffiliateConversion $action;

    protected function setUp(): void
    {
        parent::setUp();

        $badge = Mockery::mock(BadgeService::class);
        $badge->shouldReceive('evaluate')->andReturnNull();
        $this->app->instance(BadgeService::class, $badge);

        $this->action = app(RecordAffiliateConversion::class);
    }

    public function test_records_conversion_with_correct_amounts(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'REC001',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $subscription->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $referredUser->id,
            'sale_amount' => 1000,
            'platform_fee' => 98,   // 9.8% of 1000 (free plan)
            'commission_amount' => 100,
            'creator_amount' => 802,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);
    }

    public function test_increments_affiliate_total_earned(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'REC002',
            'status' => Affiliate::STATUS_ACTIVE,
            'total_earned' => 0,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $affiliate->refresh();
        $this->assertEquals(100, (float) $affiliate->total_earned);
    }

    public function test_skips_if_subscription_has_no_affiliate(): void
    {
        $referredUser = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => null,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_skips_if_affiliate_is_not_subscribed(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'REC003',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'amount' => 1000,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_math_500_sale_20_percent_rate(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 20]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'REC004',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        $referredUser = User::factory()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'affiliate_id' => $affiliate->id,
        ]);
        $payment = Payment::create([
            'subscription_id' => $subscription->id,
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $this->action->execute($subscription, $payment);

        $this->assertDatabaseHas('affiliate_conversions', [
            'sale_amount' => 500,
            'platform_fee' => 49,   // 9.8% of 500 (free plan)
            'commission_amount' => 100,
            'creator_amount' => 351,
        ]);
    }

    // ─── executeForCourse ──────────────────────────────────────────────────

    private function createCourseAffiliateSetup(float $price, int $commissionRate): array
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'price' => $price,
            'affiliate_commission_rate' => $commissionRate,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'COURSE'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        // Affiliate must be subscribed
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        return [$affiliateUser, $community, $course, $affiliate];
    }

    public function test_course_records_conversion_with_correct_amounts(): void
    {
        [$affiliateUser, $community, $course, $affiliate] = $this->createCourseAffiliateSetup(500, 15);

        $enrollment = CourseEnrollment::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'affiliate_id' => $affiliate->id,
            'status' => CourseEnrollment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCourse($enrollment);

        $this->assertNotNull($result);
        $this->assertEquals(75.0, $result['commission']);  // 15% of 500
        $this->assertEquals(500.0, $result['sale_amount']);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id' => $affiliate->id,
            'course_enrollment_id' => $enrollment->id,
            'sale_amount' => 500,
            'commission_amount' => 75,
        ]);
    }

    public function test_course_skips_if_no_affiliate(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'price' => 500,
            'affiliate_commission_rate' => 15,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'affiliate_id' => null,
            'status' => CourseEnrollment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCourse($enrollment);

        $this->assertNull($result);
        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_course_skips_if_zero_commission_rate(): void
    {
        [$affiliateUser, $community, $course, $affiliate] = $this->createCourseAffiliateSetup(500, 0);

        $enrollment = CourseEnrollment::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'affiliate_id' => $affiliate->id,
            'status' => CourseEnrollment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCourse($enrollment);

        $this->assertNull($result);
        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_course_skips_if_affiliate_not_subscribed(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'price' => 500,
            'affiliate_commission_rate' => 15,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'NOSUB'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        // No subscription for affiliateUser

        $enrollment = CourseEnrollment::create([
            'user_id' => User::factory()->create()->id,
            'course_id' => $course->id,
            'affiliate_id' => $affiliate->id,
            'status' => CourseEnrollment::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCourse($enrollment);

        $this->assertNull($result);
    }

    // ─── executeForCertification ────────────────────────────────────────────

    public function test_certification_records_conversion(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Cert',
            'cert_title' => 'Certified Tester',
            'price' => 200,
            'affiliate_commission_rate' => 20,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'CERT'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        $purchase = CertificationPurchase::create([
            'user_id' => User::factory()->create()->id,
            'certification_id' => $certification->id,
            'affiliate_id' => $affiliate->id,
            'status' => CertificationPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCertification($purchase);

        $this->assertNotNull($result);
        $this->assertEquals(40.0, $result['commission']);  // 20% of 200
        $this->assertEquals(200.0, $result['sale_amount']);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id' => $affiliate->id,
            'certification_purchase_id' => $purchase->id,
            'sale_amount' => 200,
            'commission_amount' => 40,
        ]);
    }

    public function test_certification_skips_if_no_affiliate(): void
    {
        $community = Community::factory()->create();
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Cert',
            'cert_title' => 'Certified',
            'price' => 200,
            'affiliate_commission_rate' => 20,
        ]);

        $purchase = CertificationPurchase::create([
            'user_id' => User::factory()->create()->id,
            'certification_id' => $certification->id,
            'affiliate_id' => null,
            'status' => CertificationPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCertification($purchase);

        $this->assertNull($result);
    }

    public function test_certification_skips_if_zero_commission_rate(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Cert',
            'cert_title' => 'Certified',
            'price' => 200,
            'affiliate_commission_rate' => 0,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'CERTZERO'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        $purchase = CertificationPurchase::create([
            'user_id' => User::factory()->create()->id,
            'certification_id' => $certification->id,
            'affiliate_id' => $affiliate->id,
            'status' => CertificationPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCertification($purchase);

        $this->assertNull($result);
    }

    public function test_certification_skips_if_already_recorded(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Cert',
            'cert_title' => 'Certified',
            'price' => 200,
            'affiliate_commission_rate' => 20,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'CERTDUP'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
        ]);

        $purchase = CertificationPurchase::create([
            'user_id' => User::factory()->create()->id,
            'certification_id' => $certification->id,
            'affiliate_id' => $affiliate->id,
            'status' => CertificationPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        // Pre-create the conversion
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'certification_purchase_id' => $purchase->id,
            'referred_user_id' => $purchase->user_id,
            'sale_amount' => 200,
            'platform_fee' => 20,
            'commission_amount' => 40,
            'creator_amount' => 140,
        ]);

        $result = $this->action->executeForCertification($purchase);

        $this->assertNull($result);
        // Should still have only 1 conversion
        $this->assertEquals(1, AffiliateConversion::where('certification_purchase_id', $purchase->id)->count());
    }

    // ─── executeForCurzzo ──────────────────────────────────────────────────

    private function createCurzzoAffiliateSetup(float $price, int $commissionRate, bool $subscribe = true): array
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name' => 'Test Bot',
            'instructions' => 'Be helpful.',
            'price' => $price,
            'affiliate_commission_rate' => $commissionRate,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'CURZ'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        if ($subscribe) {
            Subscription::factory()->active()->create([
                'community_id' => $community->id,
                'user_id' => $affiliateUser->id,
            ]);
        }

        return [$affiliateUser, $community, $curzzo, $affiliate];
    }

    public function test_curzzo_records_conversion(): void
    {
        [, $community, $curzzo, $affiliate] = $this->createCurzzoAffiliateSetup(300, 25);

        $purchase = CurzzoPurchase::create([
            'user_id' => User::factory()->create()->id,
            'curzzo_id' => $curzzo->id,
            'affiliate_id' => $affiliate->id,
            'status' => CurzzoPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCurzzo($purchase);

        $this->assertNotNull($result);
        $this->assertEquals(75.0, $result['commission']); // 25% of 300
        $this->assertEquals(300.0, $result['sale_amount']);

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id' => $affiliate->id,
            'curzzo_purchase_id' => $purchase->id,
            'sale_amount' => 300,
            'commission_amount' => 75,
        ]);
    }

    public function test_curzzo_skips_if_no_affiliate(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::create([
            'community_id' => $community->id,
            'name' => 'Bot',
            'instructions' => 'Help.',
            'price' => 300,
            'affiliate_commission_rate' => 25,
        ]);

        $purchase = CurzzoPurchase::create([
            'user_id' => User::factory()->create()->id,
            'curzzo_id' => $curzzo->id,
            'affiliate_id' => null,
            'status' => CurzzoPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->assertNull($this->action->executeForCurzzo($purchase));
    }

    public function test_curzzo_skips_if_zero_commission_rate(): void
    {
        [, , $curzzo, $affiliate] = $this->createCurzzoAffiliateSetup(300, 0);

        $purchase = CurzzoPurchase::create([
            'user_id' => User::factory()->create()->id,
            'curzzo_id' => $curzzo->id,
            'affiliate_id' => $affiliate->id,
            'status' => CurzzoPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->assertNull($this->action->executeForCurzzo($purchase));
        $this->assertDatabaseCount('affiliate_conversions', 0);
    }

    public function test_curzzo_skips_if_already_recorded(): void
    {
        [, , $curzzo, $affiliate] = $this->createCurzzoAffiliateSetup(300, 25);

        $purchase = CurzzoPurchase::create([
            'user_id' => User::factory()->create()->id,
            'curzzo_id' => $curzzo->id,
            'affiliate_id' => $affiliate->id,
            'status' => CurzzoPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'curzzo_purchase_id' => $purchase->id,
            'referred_user_id' => $purchase->user_id,
            'sale_amount' => 300,
            'platform_fee' => 30,
            'commission_amount' => 75,
            'creator_amount' => 195,
        ]);

        $this->assertNull($this->action->executeForCurzzo($purchase));
        $this->assertEquals(1, AffiliateConversion::where('curzzo_purchase_id', $purchase->id)->count());
    }

    public function test_curzzo_skips_if_affiliate_not_subscribed(): void
    {
        [, , $curzzo, $affiliate] = $this->createCurzzoAffiliateSetup(300, 25, subscribe: false);

        $purchase = CurzzoPurchase::create([
            'user_id' => User::factory()->create()->id,
            'curzzo_id' => $curzzo->id,
            'affiliate_id' => $affiliate->id,
            'status' => CurzzoPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->assertNull($this->action->executeForCurzzo($purchase));
    }

    public function test_certification_skips_if_affiliate_not_subscribed(): void
    {
        $affiliateUser = User::factory()->create();
        $community = Community::factory()->create();
        $certification = CourseCertification::create([
            'community_id' => $community->id,
            'title' => 'Test Cert',
            'cert_title' => 'Certified',
            'price' => 200,
            'affiliate_commission_rate' => 20,
        ]);
        $affiliate = Affiliate::create([
            'user_id' => $affiliateUser->id,
            'community_id' => $community->id,
            'code' => 'CERTNOSUB'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        // No subscription

        $purchase = CertificationPurchase::create([
            'user_id' => User::factory()->create()->id,
            'certification_id' => $certification->id,
            'affiliate_id' => $affiliate->id,
            'status' => CertificationPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $result = $this->action->executeForCertification($purchase);

        $this->assertNull($result);
    }
}
