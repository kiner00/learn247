<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\ManageLesson;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManageLessonTest extends TestCase
{
    use RefreshDatabase;

    private ManageLesson $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ManageLesson();
    }

    public function test_store_creates_lesson_with_position(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);

        $lesson = $this->action->store($module, ['title' => 'Lesson 1', 'content' => 'Content']);

        $this->assertDatabaseHas('course_lessons', [
            'id'        => $lesson->id,
            'module_id' => $module->id,
            'title'     => 'Lesson 1',
            'position'  => 1,
        ]);
    }

    public function test_store_increments_position(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);

        $lesson = $this->action->store($module, ['title' => 'L2']);

        $this->assertEquals(2, $lesson->position);
    }

    public function test_update_basic_fields(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'Old', 'position' => 1]);

        $updated = $this->action->update($lesson, ['title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
    }

    public function test_update_clears_video_path_when_video_url_provided(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/old-video.mp4', 'dummy');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Video Lesson',
            'video_path' => 'videos/old-video.mp4',
            'position'   => 1,
        ]);

        $updated = $this->action->update($lesson, [
            'title'     => 'Video Lesson',
            'video_url' => 'https://youtube.com/watch?v=abc',
        ]);

        $this->assertEquals('https://youtube.com/watch?v=abc', $updated->video_url);
        $this->assertNull($updated->video_path);
        Storage::disk('public')->assertMissing('videos/old-video.mp4');
    }
}
