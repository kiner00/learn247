<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\LessonCompletion;
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
        $course    = Course::create(['community_id' => $community->id, 'title' => 'Test Course', 'position' => 0]);
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
}
