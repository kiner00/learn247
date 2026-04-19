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
        $this->action = app(ManageCourse::class);
    }

    public function test_store_creates_course_without_cover_image(): void
    {
        $community = Community::factory()->create();

        $course = $this->action->store($community, ['title' => 'My Course', 'description' => 'Desc']);

        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'community_id' => $community->id,
            'title' => 'My Course',
            'position' => 1,
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
            'title' => 'Course',
            'cover_image' => 'old-image.jpg',
            'position' => 1,
        ]);

        $updated = $this->action->update($course, ['title' => 'Updated', 'cover_image' => 'should-be-removed']);

        $this->assertEquals('Updated', $updated->title);
        $this->assertEquals('old-image.jpg', $updated->cover_image);
    }

    public function test_reorder_updates_course_positions(): void
    {
        $community = Community::factory()->create();
        $courseA = Course::create(['community_id' => $community->id, 'title' => 'A', 'position' => 1]);
        $courseB = Course::create(['community_id' => $community->id, 'title' => 'B', 'position' => 2]);
        $courseC = Course::create(['community_id' => $community->id, 'title' => 'C', 'position' => 3]);

        $this->action->reorder($community, [$courseC->id, $courseA->id, $courseB->id]);

        $this->assertEquals(0, $courseC->fresh()->position);
        $this->assertEquals(1, $courseA->fresh()->position);
        $this->assertEquals(2, $courseB->fresh()->position);
    }

    public function test_destroy_deletes_course_without_cover_image(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'To Delete', 'position' => 1]);

        $this->action->destroy($course);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_destroy_deletes_cover_image_from_storage(): void
    {
        Storage::fake('public');
        $community = Community::factory()->create();

        $file = UploadedFile::fake()->image('cover.jpg');
        $path = $file->store('course-covers', 'public');

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'With Cover',
            'position' => 1,
            'cover_image' => asset('storage/'.$path),
        ]);

        $this->action->destroy($course);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_store_with_preview_video_converts_key_to_url(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();

        $course = $this->action->store($community, [
            'title' => 'Video Course',
            'preview_video' => 'course-previews/abc123.mp4',
        ]);

        $this->assertNotNull($course->preview_video);
        // Storage::url() is called on the S3 key
        $this->assertStringContainsString('course-previews/abc123.mp4', $course->preview_video);
    }

    public function test_store_without_preview_video_leaves_null(): void
    {
        $community = Community::factory()->create();

        $course = $this->action->store($community, ['title' => 'No Video']);

        $this->assertNull($course->preview_video);
    }

    public function test_update_with_preview_video_replaces_old(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'preview_video' => '/storage/course-previews/old.mp4',
            'position' => 1,
        ]);

        $updated = $this->action->update($course, [
            'title' => 'Course',
            'preview_video' => 'course-previews/new.mp4',
        ]);

        $this->assertStringContainsString('course-previews/new.mp4', $updated->preview_video);
    }

    public function test_update_with_remove_preview_video_clears_it(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'preview_video' => '/storage/course-previews/old.mp4',
            'position' => 1,
        ]);

        $updated = $this->action->update($course, [
            'title' => 'Course',
            'remove_preview_video' => true,
        ]);

        $this->assertNull($updated->preview_video);
    }

    public function test_update_without_preview_video_key_preserves_existing(): void
    {
        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'preview_video' => '/storage/course-previews/existing.mp4',
            'position' => 1,
        ]);

        $updated = $this->action->update($course, ['title' => 'Updated Title']);

        $this->assertEquals('/storage/course-previews/existing.mp4', $updated->preview_video);
    }

    public function test_destroy_deletes_preview_video_without_course_previews_path(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('other/vid.mp4', 'data');

        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'With Video',
            'position' => 1,
            // URL that does NOT contain 'course-previews/' — hits else branch
            'preview_video' => 'other/vid.mp4',
        ]);

        $this->action->destroy($course);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_update_with_cover_image_deletes_old_cover(): void
    {
        Storage::fake('public');
        $community = Community::factory()->create();

        // Create an existing cover
        $oldPath = UploadedFile::fake()->image('old.jpg')->store('course-covers', 'public');

        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'Course',
            'cover_image' => asset('storage/'.$oldPath),
            'position' => 1,
        ]);

        $newImage = UploadedFile::fake()->image('new.jpg');

        $this->action->update($course, ['title' => 'Updated'], $newImage);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_destroy_deletes_preview_video_with_course_previews_key(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('course-previews/vid.mp4', 'data');

        $community = Community::factory()->create();
        $course = Course::create([
            'community_id' => $community->id,
            'title' => 'With Video',
            'position' => 1,
            'preview_video' => 'https://cdn.example.com/course-previews/vid.mp4',
        ]);

        $this->action->destroy($course);

        Storage::disk($disk)->assertMissing('course-previews/vid.mp4');
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }
}
