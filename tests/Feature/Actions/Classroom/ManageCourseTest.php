<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\ManageCourse;
use App\Models\Community;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManageCourseTest extends TestCase
{
    use RefreshDatabase;

    private ManageCourse $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ManageCourse();
    }

    public function test_store_creates_course_without_cover_image(): void
    {
        $community = Community::factory()->create();

        $course = $this->action->store($community, ['title' => 'My Course', 'description' => 'Desc']);

        $this->assertDatabaseHas('courses', [
            'id'           => $course->id,
            'community_id' => $community->id,
            'title'        => 'My Course',
            'position'     => 1,
        ]);
        $this->assertNull($course->cover_image);
    }

    public function test_store_with_cover_image(): void
    {
        Storage::fake('public');
        $community = Community::factory()->create();
        $image = UploadedFile::fake()->image('cover.jpg');

        $course = $this->action->store($community, ['title' => 'Visual Course'], $image);

        $this->assertNotNull($course->cover_image);
        $this->assertStringContainsString('course-covers', $course->cover_image);
    }

    public function test_store_increments_position(): void
    {
        $community = Community::factory()->create();
        Course::create(['community_id' => $community->id, 'title' => 'First', 'position' => 1]);

        $course = $this->action->store($community, ['title' => 'Second']);

        $this->assertEquals(2, $course->position);
    }

    public function test_update_basic_fields(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'Old', 'position' => 1]);

        $updated = $this->action->update($course, ['title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
    }

    public function test_update_with_cover_image(): void
    {
        Storage::fake('public');
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $image = UploadedFile::fake()->image('new-cover.jpg');

        $updated = $this->action->update($course, ['title' => 'Course'], $image);

        $this->assertNotNull($updated->cover_image);
        $this->assertStringContainsString('course-covers', $updated->cover_image);
    }

    public function test_update_without_cover_image_unsets_field(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'cover_image'  => 'old-image.jpg',
            'position'     => 1,
        ]);

        $updated = $this->action->update($course, ['title' => 'Updated', 'cover_image' => 'should-be-removed']);

        $this->assertEquals('Updated', $updated->title);
        $this->assertEquals('old-image.jpg', $updated->cover_image);
    }
}
