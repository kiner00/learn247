<?php

namespace Tests\Unit\Models;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateConversionTest extends TestCase
{
    use RefreshDatabase;

    private function makeConversion(Community $community, User $affiliateUser, User $referredUser): array
    {
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affiliateUser->id,
            'code' => 'REF-UNIT',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $referredUser->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now(),
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $referredUser->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        return compact('affiliate', 'sub', 'payment', 'conversion');
    }

    // ─── constants ────────────────────────────────────────────────────────────────

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending', AffiliateConversion::STATUS_PENDING);
        $this->assertEquals('paid', AffiliateConversion::STATUS_PAID);
    }

    // ─── relationships ────────────────────────────────────────────────────────────

    public function test_affiliate_relationship_returns_correct_affiliate(): void
    {
        $community = Community::factory()->create();
        $result = $this->makeConversion($community, User::factory()->create(), User::factory()->create());

        $this->assertEquals($result['affiliate']->id, $result['conversion']->affiliate->id);
    }

    public function test_subscription_relationship_returns_correct_subscription(): void
    {
        $community = Community::factory()->create();
        $result = $this->makeConversion($community, User::factory()->create(), User::factory()->create());

        $this->assertEquals($result['sub']->id, $result['conversion']->subscription->id);
    }

    public function test_payment_relationship_returns_correct_payment(): void
    {
        $community = Community::factory()->create();
        $result = $this->makeConversion($community, User::factory()->create(), User::factory()->create());

        $this->assertEquals($result['payment']->id, $result['conversion']->payment->id);
    }

    public function test_referred_user_relationship_returns_correct_user(): void
    {
        $community = Community::factory()->create();
        $referredUser = User::factory()->create();
        $result = $this->makeConversion($community, User::factory()->create(), $referredUser);

        $this->assertEquals($referredUser->id, $result['conversion']->referredUser->id);
    }

    public function test_course_enrollment_relationship_returns_correct_enrollment(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $community->owner_id,
            'code' => 'REF-COURSE-UNIT',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Test Course',
            'access_type' => Course::ACCESS_PAID_ONCE,
            'price' => 500,
            'position' => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => CourseEnrollment::STATUS_PAID,
        ]);

        $conversion = AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'course_enrollment_id' => $enrollment->id,
            'referred_user_id' => $user->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 50,
            'creator_amount' => 375,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->assertEquals($enrollment->id, $conversion->courseEnrollment->id);
    }

    public function test_course_enrollment_is_null_when_not_set(): void
    {
        $community = Community::factory()->create();
        $result = $this->makeConversion($community, User::factory()->create(), User::factory()->create());

        $this->assertNull($result['conversion']->courseEnrollment);
    }
}
