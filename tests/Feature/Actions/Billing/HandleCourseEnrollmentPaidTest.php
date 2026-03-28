<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Billing\WebhookHandlers\HandleCourseEnrollmentPaid;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HandleCourseEnrollmentPaidTest extends TestCase
{
    use RefreshDatabase;

    // ── matches() ────────────────────────────────────────────────────────────

    public function test_matches_returns_true_for_existing_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_enroll_match',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $this->assertTrue($handler->matches('inv_enroll_match'));
    }

    public function test_matches_returns_false_for_nonexistent_xendit_id(): void
    {
        $handler = app(HandleCourseEnrollmentPaid::class);
        $this->assertFalse($handler->matches('inv_no_enrollment'));
    }

    // ── handle() non-paid status early return ────────────────────────────────

    public function test_non_paid_status_returns_early_without_updating_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_enroll_fail',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_enroll_fail');
        $handler->handle(
            ['id' => 'inv_enroll_fail', 'status' => 'FAILED', 'amount' => 500],
            'evt_fail',
            'FAILED'
        );

        $this->assertDatabaseHas('course_enrollments', [
            'xendit_id' => 'inv_enroll_fail',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);
    }

    public function test_expired_status_returns_early(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_enroll_exp',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_enroll_exp');
        $handler->handle(
            ['id' => 'inv_enroll_exp', 'status' => 'EXPIRED', 'amount' => 500],
            'evt_exp',
            'EXPIRED'
        );

        $this->assertDatabaseHas('course_enrollments', [
            'xendit_id' => 'inv_enroll_exp',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);
    }

    public function test_pending_status_returns_early(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_enroll_pend',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_enroll_pend');
        $handler->handle(
            ['id' => 'inv_enroll_pend', 'status' => 'PROCESSING', 'amount' => 500],
            'evt_pend',
            'PROCESSING'
        );

        $this->assertDatabaseHas('course_enrollments', [
            'xendit_id' => 'inv_enroll_pend',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);
    }

    // ── paid_once enrollment ─────────────────────────────────────────────────

    public function test_paid_once_enrollment_sets_no_expiry(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Once Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_once_paid',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_once_paid');
        $handler->handle(
            ['id' => 'inv_once_paid', 'status' => 'PAID', 'amount' => 500],
            'evt_once',
            'PAID'
        );

        $enrollment->refresh();
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
        $this->assertNotNull($enrollment->paid_at);
        $this->assertNull($enrollment->expires_at);
    }

    // ── monthly enrollment sets expiry ───────────────────────────────────────

    public function test_monthly_enrollment_sets_future_expiry(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_monthly_paid',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_monthly_paid');
        $handler->handle(
            ['id' => 'inv_monthly_paid', 'status' => 'PAID', 'amount' => 200],
            'evt_monthly',
            'PAID'
        );

        $enrollment->refresh();
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
        $this->assertNotNull($enrollment->expires_at);
        $this->assertTrue($enrollment->expires_at->isFuture());
    }

    // ── monthly renewal extends from future expiry ───────────────────────────

    public function test_monthly_renewal_extends_from_existing_future_expiry(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $futureExpiry = now()->addDays(10);
        $enrollment = CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'xendit_id'  => 'inv_monthly_renew',
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => $futureExpiry,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_monthly_renew');
        $handler->handle(
            ['id' => 'inv_monthly_renew', 'status' => 'PAID', 'amount' => 200],
            'evt_m_renew',
            'PAID'
        );

        $enrollment->refresh();
        $expectedExpiry = $futureExpiry->copy()->addMonth();
        $this->assertTrue(
            abs($enrollment->expires_at->diffInSeconds($expectedExpiry)) < 5,
            'Monthly renewal should extend from existing future expiry'
        );
    }

    // ── SETTLED maps to PAID ─────────────────────────────────────────────────

    public function test_settled_status_marks_enrollment_as_paid(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Settled Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_settled_enroll',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_settled_enroll');
        $handler->handle(
            ['id' => 'inv_settled_enroll', 'status' => 'SETTLED', 'amount' => 500],
            'evt_settled',
            'SETTLED'
        );

        $enrollment->refresh();
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
    }

    // ── affiliate conversion and cha-ching ───────────────────────────────────

    public function test_records_affiliate_conversion_and_sends_cha_ching(): void
    {
        Mail::fake();

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'affiliate_commission_rate' => 10]);

        $affiliateUser = User::factory()->create();
        $affiliate     = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'code'         => 'AFF_COURSE_1',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        // Affiliate must have an active subscription to earn
        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $affiliateUser->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => null,
        ]);

        $course = Course::create([
            'community_id'              => $community->id,
            'title'                     => 'Affiliate Course',
            'access_type'               => Course::ACCESS_PAID_ONCE,
            'price'                     => 500,
            'affiliate_commission_rate' => 10,
            'position'                  => 1,
        ]);

        $buyer = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'user_id'      => $buyer->id,
            'course_id'    => $course->id,
            'affiliate_id' => $affiliate->id,
            'xendit_id'    => 'inv_course_aff',
            'status'       => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_course_aff');
        $handler->handle(
            ['id' => 'inv_course_aff', 'status' => 'PAID', 'amount' => 500],
            'evt_course_aff',
            'PAID'
        );

        $this->assertDatabaseHas('affiliate_conversions', [
            'affiliate_id'         => $affiliate->id,
            'course_enrollment_id' => $enrollment->id,
        ]);

        Mail::assertQueued(\App\Mail\AffiliateChaChing::class);
        Mail::assertQueued(\App\Mail\CreatorChaChing::class);
    }

    // ── monthly enrollment with past expiry renews from now ─────────────────

    public function test_monthly_renewal_from_past_expiry_sets_from_now(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'xendit_id'  => 'inv_monthly_past',
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => now()->subDays(5),
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_monthly_past');
        $handler->handle(
            ['id' => 'inv_monthly_past', 'status' => 'PAID', 'amount' => 200],
            'evt_m_past',
            'PAID'
        );

        $enrollment->refresh();
        $expectedExpiry = now()->addMonth();
        $this->assertTrue(
            abs($enrollment->expires_at->diffInSeconds($expectedExpiry)) < 60,
            'Should set expiry from now when previous expiry is in the past'
        );
    }

    // ── monthly enrollment with null expiry sets from now ─────────────────

    public function test_monthly_with_null_expiry_sets_from_now(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'xendit_id'  => 'inv_monthly_null',
            'status'     => CourseEnrollment::STATUS_PENDING,
            'expires_at' => null,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_monthly_null');
        $handler->handle(
            ['id' => 'inv_monthly_null', 'status' => 'PAID', 'amount' => 200],
            'evt_m_null',
            'PAID'
        );

        $enrollment->refresh();
        $this->assertNotNull($enrollment->expires_at);
        $this->assertTrue($enrollment->expires_at->isFuture());
    }

    // ── no affiliate skips cha-ching ─────────────────────────────────────────

    public function test_no_affiliate_skips_conversion_and_cha_ching(): void
    {
        Mail::fake();

        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'No Aff Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $buyer = User::factory()->create();
        $enrollment = CourseEnrollment::create([
            'user_id'   => $buyer->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_no_aff_course',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_no_aff_course');
        $handler->handle(
            ['id' => 'inv_no_aff_course', 'status' => 'PAID', 'amount' => 500],
            'evt_no_aff',
            'PAID'
        );

        $enrollment->refresh();
        $this->assertEquals(CourseEnrollment::STATUS_PAID, $enrollment->status);
        Mail::assertNothingQueued();
    }

    // ── catch/rethrow block (lines 69-74) ───────────────────────────────────

    public function test_handle_logs_error_and_rethrows_on_exception(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Error Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_enroll_err',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        // Make RecordAffiliateConversion throw to trigger the catch block
        $this->mock(RecordAffiliateConversion::class)
            ->shouldReceive('executeForCourse')
            ->andThrow(new \RuntimeException('conversion recording failed'));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($msg, $ctx) => $msg === 'HandleCourseEnrollmentPaid failed'
                && $ctx['enrollment_id'] === $enrollment->id
                && str_contains($ctx['error'], 'conversion recording failed'));
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('conversion recording failed');

        $handler = app(HandleCourseEnrollmentPaid::class);
        $handler->matches('inv_enroll_err');
        $handler->handle(
            ['id' => 'inv_enroll_err', 'status' => 'PAID', 'amount' => 500],
            'evt_enroll_err',
            'PAID'
        );
    }
}
