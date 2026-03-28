<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetEnrolledCoursesTool;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetEnrolledCoursesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_no_courses_message_when_user_has_none(): void
    {
        $user = User::factory()->create();
        $tool = new GetEnrolledCoursesTool($user->id);

        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('not enrolled', $result);
    }

    public function test_returns_enrolled_courses_with_progress(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $course    = Course::factory()->create(['community_id' => $community->id, 'title' => 'Advanced PHP']);
        $module    = CourseModule::factory()->create(['course_id' => $course->id]);
        $lesson1   = CourseLesson::factory()->create(['module_id' => $module->id]);
        $lesson2   = CourseLesson::factory()->create(['module_id' => $module->id]);

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PAID,
            'paid_at'   => now()->subDays(5),
        ]);

        LessonCompletion::create(['user_id' => $user->id, 'lesson_id' => $lesson1->id]);

        $tool   = new GetEnrolledCoursesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json   = json_decode($result, true);

        $this->assertCount(1, $json);
        $this->assertSame('Advanced PHP', $json[0]['course']);
        $this->assertSame($community->name, $json[0]['community']);
        $this->assertSame(1, $json[0]['lessons_done']);
        $this->assertSame(2, $json[0]['lessons_total']);
    }

    public function test_excludes_pending_enrollments(): void
    {
        $user   = User::factory()->create();
        $course = Course::factory()->create();

        CourseEnrollment::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'status'    => CourseEnrollment::STATUS_PENDING,
        ]);

        $tool   = new GetEnrolledCoursesTool($user->id);
        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('not enrolled', $result);
    }

    public function test_includes_expiry_date(): void
    {
        $user   = User::factory()->create();
        $course = Course::factory()->create();

        CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => CourseEnrollment::STATUS_PAID,
            'paid_at'    => now()->subDays(10),
            'expires_at' => now()->addDays(20),
        ]);

        $tool   = new GetEnrolledCoursesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json   = json_decode($result, true);

        $this->assertNotNull($json[0]['expires_at']);
        $this->assertNotNull($json[0]['enrolled_at']);
    }

    public function test_returns_multiple_enrollments(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $course = Course::factory()->create();
            CourseEnrollment::create([
                'user_id'   => $user->id,
                'course_id' => $course->id,
                'status'    => CourseEnrollment::STATUS_PAID,
                'paid_at'   => now(),
            ]);
        }

        $tool   = new GetEnrolledCoursesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json   = json_decode($result, true);

        $this->assertCount(3, $json);
    }

    public function test_description_returns_string(): void
    {
        $tool = new GetEnrolledCoursesTool(1);
        $this->assertIsString($tool->description());
    }

    public function test_schema_returns_empty_array(): void
    {
        $tool   = new GetEnrolledCoursesTool(1);
        $schema = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);

        $this->assertSame([], $tool->schema($schema));
    }
}
