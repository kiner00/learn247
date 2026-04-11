<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Classroom\CourseAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private CourseAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CourseAccessService();
    }

    // ── FREE courses ──────────────────────────────────────────────────────────

    public function test_free_course_denies_access_to_guest(): void
    {
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_FREE]);

        $this->assertFalse($this->service->hasAccess(null, $community, $course));
    }

    public function test_free_course_requires_membership(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_FREE]);

        // Without membership — denied
        $this->assertFalse($this->service->hasAccess($user, $community, $course));

        // With membership — granted
        \App\Models\CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    // ── Guest (null user) on non-free courses ─────────────────────────────────

    public function test_guest_denied_inclusive_course(): void
    {
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        $this->assertFalse($this->service->hasAccess(null, $community, $course));
    }

    public function test_guest_denied_paid_once_course(): void
    {
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        $this->assertFalse($this->service->hasAccess(null, $community, $course));
    }

    public function test_guest_denied_paid_monthly_course(): void
    {
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_MONTHLY]);

        $this->assertFalse($this->service->hasAccess(null, $community, $course));
    }

    // ── Owner bypass ──────────────────────────────────────────────────────────

    public function test_owner_always_has_access_to_inclusive_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        $this->assertTrue($this->service->hasAccess($owner, $community, $course));
    }

    public function test_owner_always_has_access_to_paid_once_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        $this->assertTrue($this->service->hasAccess($owner, $community, $course));
    }

    // ── Super admin bypass ────────────────────────────────────────────────────

    public function test_super_admin_always_has_access(): void
    {
        $admin     = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        $this->assertTrue($this->service->hasAccess($admin, $community, $course));
    }

    // ── INCLUSIVE courses ─────────────────────────────────────────────────────

    public function test_inclusive_course_granted_with_active_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'expires_at'   => now()->addMonth(),
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_inclusive_course_granted_with_lifetime_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => null,
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_inclusive_course_denied_with_expired_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_EXPIRED,
            'expires_at'   => now()->subDay(),
        ]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    public function test_inclusive_course_denied_with_no_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    public function test_inclusive_course_denied_with_subscription_to_different_community(): void
    {
        $user       = User::factory()->create();
        $community  = Community::factory()->create();
        $other      = Community::factory()->create();
        $course     = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_INCLUSIVE]);

        Subscription::factory()->active()->create(['community_id' => $other->id, 'user_id' => $user->id]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    // ── PAID_ONCE courses ─────────────────────────────────────────────────────

    public function test_paid_once_course_granted_with_paid_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PAID,
            'paid_at'   => now(),
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_paid_once_course_denied_with_pending_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    public function test_paid_once_course_denied_with_expired_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    public function test_paid_once_course_denied_with_no_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_ONCE]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    // ── PAID_MONTHLY courses ──────────────────────────────────────────────────

    public function test_paid_monthly_course_granted_with_non_expired_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_MONTHLY]);

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => now()->addMonth(),
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_paid_monthly_course_denied_with_expired_enrollment(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_PAID_MONTHLY]);

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    // ── MEMBER_ONCE courses ──────────────────────────────────────────────────

    public function test_member_once_granted_with_active_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_MEMBER_ONCE]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_member_once_granted_with_expired_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_MEMBER_ONCE]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_EXPIRED,
            'expires_at'   => now()->subDay(),
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_member_once_granted_with_cancelled_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_MEMBER_ONCE]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_CANCELLED,
            'expires_at'   => now()->subDay(),
        ]);

        $this->assertTrue($this->service->hasAccess($user, $community, $course));
    }

    public function test_member_once_denied_with_pending_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_MEMBER_ONCE]);

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_PENDING,
        ]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    public function test_member_once_denied_with_no_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_MEMBER_ONCE]);

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }

    public function test_guest_denied_member_once_course(): void
    {
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'access_type' => Course::ACCESS_MEMBER_ONCE]);

        $this->assertFalse($this->service->hasAccess(null, $community, $course));
    }

    // ── Unknown access type ───────────────────────────────────────────────────

    public function test_unknown_access_type_denies_access(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id]);
        $course->access_type = 'unknown_type'; // force an unrecognised value

        $this->assertFalse($this->service->hasAccess($user, $community, $course));
    }
}
