<?php

namespace Tests\Feature\Resources;

use App\Http\Resources\CourseResource;
use App\Models\Community;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_resource_returns_expected_keys(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Test Course',
            'description' => 'A description',
            'position' => 1,
        ]);

        $resource = (new CourseResource($course))->toArray(request());

        $this->assertArrayHasKey('id', $resource);
        $this->assertArrayHasKey('title', $resource);
        $this->assertArrayHasKey('description', $resource);
        $this->assertArrayHasKey('position', $resource);
        $this->assertArrayHasKey('created_at', $resource);
    }

    public function test_course_resource_returns_correct_values(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Laravel Basics',
            'description' => 'Learn Laravel',
            'position' => 3,
        ]);

        $resource = (new CourseResource($course))->toArray(request());

        $this->assertSame($course->id, $resource['id']);
        $this->assertSame('Laravel Basics', $resource['title']);
        $this->assertSame('Learn Laravel', $resource['description']);
        $this->assertSame(3, $resource['position']);
    }

    public function test_course_resource_omits_modules_when_not_loaded(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Test',
            'description' => 'Desc',
            'position' => 0,
        ]);

        $resource = (new CourseResource($course))->resolve();

        $this->assertArrayNotHasKey('modules', $resource);
    }

    public function test_course_resource_includes_modules_when_loaded(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'With Modules',
            'description' => 'Desc',
            'position' => 0,
        ]);

        $course->load('modules');
        $resource = (new CourseResource($course))->toArray(request());

        $this->assertArrayHasKey('modules', $resource);
    }

    public function test_course_resource_includes_modules_with_lessons_when_loaded(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Full Course',
            'description' => 'With modules and lessons',
            'position' => 0,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title' => 'Module 1',
            'position' => 1,
        ]);
        CourseLesson::create([
            'module_id' => $module->id,
            'title' => 'Lesson 1',
            'position' => 1,
            'video_url' => 'https://example.com/video.mp4',
        ]);

        $course->load('modules.lessons');
        $resource = (new CourseResource($course))->toArray(request());

        $this->assertArrayHasKey('modules', $resource);
        $this->assertCount(1, $resource['modules']);
        $this->assertArrayHasKey('lessons', $resource['modules'][0]);
        $this->assertCount(1, $resource['modules'][0]['lessons']);
        $this->assertSame('Lesson 1', $resource['modules'][0]['lessons'][0]['title']);
        $this->assertSame('https://example.com/video.mp4', $resource['modules'][0]['lessons'][0]['video_url']);
    }
}
