<?php

namespace Tests\Unit\Models;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(?Community $community = null): Course
    {
        $community ??= Community::factory()->create();

        return Course::create([
            'community_id' => $community->id,
            'title' => 'Test Course',
            'access_type' => Course::ACCESS_FREE,
            'position' => 1,
        ]);
    }

    // ─── constants ────────────────────────────────────────────────────────────────

    public function test_access_type_constants_are_defined(): void
    {
        $this->assertEquals('free', Course::ACCESS_FREE);
        $this->assertEquals('inclusive', Course::ACCESS_INCLUSIVE);
        $this->assertEquals('paid_once', Course::ACCESS_PAID_ONCE);
        $this->assertEquals('paid_monthly', Course::ACCESS_PAID_MONTHLY);
    }

    // ─── relationships ────────────────────────────────────────────────────────────

    public function test_community_relationship_returns_correct_community(): void
    {
        $community = Community::factory()->create();
        $course = $this->makeCourse($community);

        $this->assertEquals($community->id, $course->community->id);
    }

    public function test_modules_relationship_returns_ordered_modules(): void
    {
        $course = $this->makeCourse();
        $module1 = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $module2 = CourseModule::create(['course_id' => $course->id, 'title' => 'M2', 'position' => 0]);

        $modules = $course->modules;

        $this->assertCount(2, $modules);
        $this->assertEquals($module2->id, $modules->first()->id); // ordered by position
    }

    public function test_lessons_relationship_returns_all_lessons_through_modules(): void
    {
        $course = $this->makeCourse();
        $module1 = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 0]);
        $module2 = CourseModule::create(['course_id' => $course->id, 'title' => 'M2', 'position' => 1]);

        CourseLesson::create(['module_id' => $module1->id, 'title' => 'L1', 'position' => 0]);
        CourseLesson::create(['module_id' => $module2->id, 'title' => 'L2', 'position' => 0]);

        $this->assertCount(2, $course->lessons);
    }

    public function test_enrollments_relationship_returns_course_enrollments(): void
    {
        $course = $this->makeCourse();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        CourseEnrollment::create(['user_id' => $user1->id, 'course_id' => $course->id, 'status' => CourseEnrollment::STATUS_PAID]);
        CourseEnrollment::create(['user_id' => $user2->id, 'course_id' => $course->id, 'status' => CourseEnrollment::STATUS_PENDING]);

        $this->assertCount(2, $course->enrollments);
    }
}
