<?php

namespace Tests\Unit\Models;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    // ─── constants ───────────────────────────────────────────────────────────────

    public function test_status_constants_are_defined(): void
    {
        $this->assertEquals('pending', CourseEnrollment::STATUS_PENDING);
        $this->assertEquals('paid', CourseEnrollment::STATUS_PAID);
    }

    public function test_paid_at_and_expires_at_are_cast_to_datetime(): void
    {
        $casts = (new CourseEnrollment)->getCasts();

        $this->assertArrayHasKey('paid_at', $casts);
        $this->assertEquals('datetime', $casts['paid_at']);

        $this->assertArrayHasKey('expires_at', $casts);
        $this->assertEquals('datetime', $casts['expires_at']);
    }

    // ─── relationships ────────────────────────────────────────────────────────────

    public function test_user_relationship_returns_correct_user(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
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
            'status' => CourseEnrollment::STATUS_PENDING,
        ]);

        $this->assertEquals($user->id, $enrollment->user->id);
    }

    public function test_course_relationship_returns_correct_course(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
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
            'status' => CourseEnrollment::STATUS_PENDING,
        ]);

        $this->assertEquals($course->id, $enrollment->course->id);
    }

    public function test_affiliate_relationship_returns_correct_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Test Course',
            'access_type' => Course::ACCESS_PAID_ONCE,
            'price' => 500,
            'position' => 1,
        ]);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $community->owner_id,
            'code' => 'REF-MODEL',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'affiliate_id' => $affiliate->id,
            'status' => CourseEnrollment::STATUS_PENDING,
        ]);

        $this->assertEquals($affiliate->id, $enrollment->affiliate->id);
    }

    public function test_affiliate_relationship_is_null_when_no_affiliate(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
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
            'status' => CourseEnrollment::STATUS_PENDING,
        ]);

        $this->assertNull($enrollment->affiliate);
    }
}
