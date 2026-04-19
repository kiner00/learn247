<?php

namespace Tests\Feature\Queries\Classroom;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\User;
use App\Queries\Classroom\GetCourseDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCourseDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_enrollment_for_authenticated_user_with_access(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        CourseLesson::factory()->create(['module_id' => $module->id]);

        $enrollment = CourseEnrollment::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => CourseEnrollment::STATUS_PAID,
        ]);

        $query = new GetCourseDetail;
        $result = $query->execute($course, $user->id, true);

        $this->assertNotNull($result['enrollment']);
        $this->assertEquals('paid', $result['enrollment']['status']);
    }
}
