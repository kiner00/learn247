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
        $this->action = app(ManageLesson::class);
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
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('videos/old-video.mp4', 'dummy');

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
        Storage::disk($disk)->assertMissing('videos/old-video.mp4');
    }

    public function test_update_replaces_old_video_path_with_new_one(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/old.mp4', 'old content');
        Storage::disk($disk)->put('lesson-videos/new.mp4', 'new content');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Video Lesson',
            'video_path' => 'lesson-videos/old.mp4',
            'position'   => 1,
        ]);

        $updated = $this->action->update($lesson, [
            'video_path' => 'lesson-videos/new.mp4',
        ]);

        $this->assertEquals('lesson-videos/new.mp4', $updated->video_path);
        Storage::disk($disk)->assertMissing('lesson-videos/old.mp4');
        Storage::disk($disk)->assertExists('lesson-videos/new.mp4');
    }

    public function test_upload_video_stores_with_private_visibility(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        $video = \Illuminate\Http\UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');

        $path = $this->action->uploadVideo($video);

        $this->assertStringStartsWith('lesson-videos/', $path);
        Storage::disk($disk)->assertExists($path);
    }

    public function test_upload_image_stores_and_returns_url(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        $image = \Illuminate\Http\UploadedFile::fake()->image('lesson.jpg');

        $url = $this->action->uploadImage($image);

        $this->assertNotEmpty($url);
        $files = Storage::disk($disk)->allFiles('lesson-images');
        $this->assertCount(1, $files);
    }

    public function test_reorder_updates_lesson_positions(): void
    {
        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $l1 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);
        $l2 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L2', 'position' => 2]);
        $l3 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L3', 'position' => 3]);

        // Reverse order
        $this->action->reorder($module, [$l3->id, $l2->id, $l1->id]);

        $this->assertEquals(0, $l3->fresh()->position);
        $this->assertEquals(1, $l2->fresh()->position);
        $this->assertEquals(2, $l1->fresh()->position);
    }

    public function test_update_explicitly_removing_video_path_clears_fields(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/to-remove.mp4', 'data');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'                 => $module->id,
            'title'                     => 'Video Lesson',
            'video_path'                => 'lesson-videos/to-remove.mp4',
            'video_hls_path'            => 'hls/to-remove/index.m3u8',
            'video_transcode_status'    => 'completed',
            'video_transcode_percent'   => 100,
            'position'                  => 1,
        ]);

        $updated = $this->action->update($lesson, ['video_path' => '']);

        $this->assertNull($updated->video_path);
        $this->assertNull($updated->video_hls_path);
        $this->assertNull($updated->video_transcode_status);
        $this->assertEquals(0, $updated->video_transcode_percent);
        Storage::disk($disk)->assertMissing('lesson-videos/to-remove.mp4');
    }

    public function test_update_setting_new_video_path_when_none_exists(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/new.mp4', 'content');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'No Video Yet',
            'position'  => 1,
        ]);

        $updated = $this->action->update($lesson, ['video_path' => 'lesson-videos/new.mp4']);

        $this->assertEquals('lesson-videos/new.mp4', $updated->video_path);
        $this->assertNull($updated->video_hls_path);
        $this->assertNull($updated->video_transcode_status);
        $this->assertEquals(0, $updated->video_transcode_percent);
    }

    public function test_update_with_video_url_resets_transcode_fields(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/uploaded.mp4', 'data');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'                 => $module->id,
            'title'                     => 'Video Lesson',
            'video_path'                => 'lesson-videos/uploaded.mp4',
            'video_hls_path'            => 'hls/uploaded/index.m3u8',
            'video_transcode_status'    => 'completed',
            'video_transcode_percent'   => 100,
            'position'                  => 1,
        ]);

        $updated = $this->action->update($lesson, [
            'video_url' => 'https://youtube.com/watch?v=xyz',
        ]);

        $this->assertEquals('https://youtube.com/watch?v=xyz', $updated->video_url);
        $this->assertNull($updated->video_path);
        $this->assertNull($updated->video_hls_path);
        $this->assertNull($updated->video_transcode_status);
        $this->assertEquals(0, $updated->video_transcode_percent);
    }

    public function test_update_deletes_hls_files_when_replacing_video(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/old.mp4', 'old');
        Storage::disk($disk)->put('lesson-videos/new.mp4', 'new');
        Storage::disk($disk)->put('hls/old-lesson/index.m3u8', 'playlist');
        Storage::disk($disk)->put('hls/old-lesson/segment1.ts', 'segment');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'      => $module->id,
            'title'          => 'Video Lesson',
            'video_path'     => 'lesson-videos/old.mp4',
            'video_hls_path' => 'hls/old-lesson/index.m3u8',
            'position'       => 1,
        ]);

        $this->action->update($lesson, ['video_path' => 'lesson-videos/new.mp4']);

        Storage::disk($disk)->assertMissing('hls/old-lesson/index.m3u8');
        Storage::disk($disk)->assertMissing('hls/old-lesson/segment1.ts');
    }

    public function test_update_same_video_path_does_not_delete(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);
        Storage::disk($disk)->put('lesson-videos/same.mp4', 'data');

        $community = Community::factory()->create();
        $course = Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Video Lesson',
            'video_path' => 'lesson-videos/same.mp4',
            'position'   => 1,
        ]);

        $updated = $this->action->update($lesson, ['video_path' => 'lesson-videos/same.mp4']);

        $this->assertEquals('lesson-videos/same.mp4', $updated->video_path);
        Storage::disk($disk)->assertExists('lesson-videos/same.mp4');
    }
}
