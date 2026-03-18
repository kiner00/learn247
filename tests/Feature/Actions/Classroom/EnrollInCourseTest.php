<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\EnrollInCourse;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class EnrollInCourseTest extends TestCase
{
    use RefreshDatabase;

    private function mockXendit(): MockInterface
    {
        return $this->mock(XenditService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createInvoice')->andReturn([
                'id'          => 'inv_course_test_123',
                'invoice_url' => 'https://checkout.xendit.co/inv_course_test_123',
            ]);
        });
    }

    // ─── paid_once course ────────────────────────────────────────────────────────

    public function test_creates_pending_enrollment_and_returns_checkout_url_for_paid_once(): void
    {
        $this->mockXendit();

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $result = $action->execute($user, $community, $course, 'https://example.com/success');

        $this->assertArrayHasKey('enrollment', $result);
        $this->assertArrayHasKey('checkout_url', $result);
        $this->assertEquals('https://checkout.xendit.co/inv_course_test_123', $result['checkout_url']);

        $this->assertDatabaseHas('course_enrollments', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);
    }

    public function test_creates_pending_enrollment_for_paid_monthly(): void
    {
        $this->mockXendit();

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $result = $action->execute($user, $community, $course, 'https://example.com/success');

        $this->assertDatabaseHas('course_enrollments', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);
        $this->assertEquals('https://checkout.xendit.co/inv_course_test_123', $result['checkout_url']);
    }

    // ─── validation ─────────────────────────────────────────────────────────────

    public function test_throws_when_course_is_free(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Free Course',
            'access_type'  => Course::ACCESS_FREE,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_throws_when_course_is_inclusive(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_throws_when_user_already_has_active_paid_enrollment(): void
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
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => null,
        ]);

        $action = app(EnrollInCourse::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_throws_when_user_has_active_monthly_enrollment_not_expired(): void
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

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => now()->addDays(15),
        ]);

        $action = app(EnrollInCourse::class);

        $this->expectException(ValidationException::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_allows_enrollment_when_monthly_enrollment_is_expired(): void
    {
        $this->mockXendit();

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => Course::ACCESS_PAID_MONTHLY,
            'price'        => 200,
            'position'     => 1,
        ]);

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => now()->subDay(),
        ]);

        $action = app(EnrollInCourse::class);
        $result = $action->execute($user, $community, $course, 'https://example.com/success');

        $this->assertArrayHasKey('checkout_url', $result);
    }

    public function test_upserts_existing_pending_enrollment(): void
    {
        $this->mockXendit();

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        // Pre-existing pending enrollment
        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'old_inv_123',
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');

        // Should upsert, not create a second record
        $this->assertEquals(1, CourseEnrollment::where('user_id', $user->id)->where('course_id', $course->id)->count());
        $this->assertDatabaseHas('course_enrollments', [
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'xendit_id' => 'inv_course_test_123',
        ]);
    }

    // ─── affiliate tracking ──────────────────────────────────────────────────────

    public function test_affiliate_id_is_set_from_active_subscription(): void
    {
        $this->mockXendit();

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $affiliate = \App\Models\Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $community->owner_id,
            'code'         => 'REF-TRACK',
            'status'       => \App\Models\Affiliate::STATUS_ACTIVE,
        ]);

        \App\Models\Subscription::factory()->active()->create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'affiliate_id' => $affiliate->id,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');

        $this->assertDatabaseHas('course_enrollments', [
            'user_id'      => $user->id,
            'course_id'    => $course->id,
            'affiliate_id' => $affiliate->id,
        ]);
    }

    public function test_affiliate_id_is_null_when_user_has_no_subscription(): void
    {
        $this->mockXendit();

        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');

        $this->assertDatabaseHas('course_enrollments', [
            'user_id'      => $user->id,
            'course_id'    => $course->id,
            'affiliate_id' => null,
        ]);
    }

    public function test_community_currency_is_used_in_invoice(): void
    {
        $xenditMock = $this->mock(XenditService::class, function (\Mockery\MockInterface $mock) {
            $mock->shouldReceive('createInvoice')
                ->withArgs(function (array $payload) {
                    return $payload['currency'] === 'USD';
                })
                ->once()
                ->andReturn([
                    'id'          => 'inv_usd_123',
                    'invoice_url' => 'https://checkout.xendit.co/inv_usd_123',
                ]);
        });

        $user      = User::factory()->create();
        $community = Community::factory()->create(['currency' => 'USD']);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'USD Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 999,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }

    public function test_community_default_currency_php_is_used_in_invoice(): void
    {
        $xenditMock = $this->mock(XenditService::class, function (\Mockery\MockInterface $mock) {
            $mock->shouldReceive('createInvoice')
                ->withArgs(function (array $payload) {
                    return $payload['currency'] === 'PHP';
                })
                ->once()
                ->andReturn([
                    'id'          => 'inv_php_123',
                    'invoice_url' => 'https://checkout.xendit.co/inv_php_123',
                ]);
        });

        $user      = User::factory()->create();
        // Community factory defaults to 'PHP' currency
        $community = Community::factory()->create(['currency' => 'PHP']);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'PHP Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        $action = app(EnrollInCourse::class);
        $action->execute($user, $community, $course, 'https://example.com/success');
    }
}
