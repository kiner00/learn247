<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Classroom\GetCourseList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCourseListTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_returns_course_list_with_progress(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $course    = Course::create(['community_id' => $community->id, 'title' => 'Test Course', 'position' => 0, 'access_type' => 'free']);
        $module    = CourseModule::create(['course_id' => $course->id, 'title' => 'Module 1', 'position' => 0]);
        $lesson    = CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson 1', 'position' => 0]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson->id]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $user->id);

        $this->assertCount(1, $result);
        $this->assertSame('Test Course', $result->first()['title']);
        $this->assertSame(1, $result->first()['total']);
        $this->assertSame(1, $result->first()['completed']);
        $this->assertEquals(100, $result->first()['progress']);
    }

    public function test_execute_returns_zero_progress_when_no_lessons(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();

        Course::create(['community_id' => $community->id, 'title' => 'Empty Course', 'position' => 0]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $user->id);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result->first()['total']);
        $this->assertSame(0, $result->first()['completed']);
        $this->assertSame(0, $result->first()['progress']);
    }

    public function test_execute_with_null_user_returns_no_access_and_no_progress(): void
    {
        $community = Community::factory()->create();
        Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 0,
        ]);

        $query  = new GetCourseList();
        $result = $query->execute($community, null);

        $this->assertCount(1, $result);
        $this->assertFalse($result->first()['has_access']);
        $this->assertSame(0, $result->first()['completed']);
    }

    public function test_execute_owner_has_access_to_all_courses(): void
    {
        $community = Community::factory()->create();
        $owner     = User::find($community->owner_id);

        Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 0,
        ]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $owner->id);

        $this->assertTrue($result->first()['has_access']);
    }

    public function test_execute_inclusive_course_accessible_for_active_member(): void
    {
        $community = Community::factory()->paid()->create();
        $member    = User::factory()->create();

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 0,
        ]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $member->id);

        $this->assertTrue($result->first()['has_access']);
    }

    public function test_execute_inclusive_course_not_accessible_for_non_member(): void
    {
        $community = Community::factory()->paid()->create();
        $user      = User::factory()->create();

        Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 0,
        ]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $user->id);

        $this->assertFalse($result->first()['has_access']);
    }

    public function test_execute_paid_once_course_accessible_when_enrolled(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Once Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 0,
        ]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PAID,
        ]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $user->id);

        $this->assertTrue($result->first()['has_access']);
    }

    public function test_execute_paid_once_course_not_accessible_without_enrollment(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();

        Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Once Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 0,
        ]);

        $query  = new GetCourseList();
        $result = $query->execute($community, $user->id);

        $this->assertFalse($result->first()['has_access']);
    }

    public function test_resolve_access_returns_false_for_unknown_access_type(): void
    {
        // The final `return false` in resolveAccess() is unreachable via normal DB insertion
        // (the schema CHECK constraint blocks it), so we test via reflection with an
        // in-memory Course whose access_type is not one of the known constants.
        $course = new Course();
        $course->access_type = 'unknown_type';

        $query      = new GetCourseList();
        $reflection = new \ReflectionMethod(GetCourseList::class, 'resolveAccess');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($query, $course, false, false, collect());

        $this->assertFalse($result);
    }
}
