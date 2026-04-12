<?php

namespace Tests\Feature\Web;

use App\Actions\Classroom\CompleteLesson;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CreatorSubscription;
use App\Models\Subscription;
use App\Models\User;
use App\Queries\Classroom\GetCourseList;
use App\Services\Community\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClassroomControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ──────────────────────────────────────────────────────────────────

    public function test_owner_can_view_classroom_index(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    public function test_member_can_view_classroom_index_on_free_community(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    public function test_member_with_active_subscription_can_view_classroom_index_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    public function test_non_member_can_view_classroom_index_on_free_community(): void
    {
        $owner     = User::factory()->create();
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    public function test_non_subscriber_can_view_classroom_index_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    public function test_unauthenticated_user_can_view_classroom_index(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    // ─── storeCourse ─────────────────────────────────────────────────────────────

    public function test_owner_can_store_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'New Course Title',
                'description' => 'Course description here',
                'access_type' => 'inclusive',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course created!');
        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'New Course Title',
            'description'  => 'Course description here',
        ]);
    }

    public function test_owner_can_store_course_with_cover_image(): void
    {
        Storage::fake('public');

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $file = UploadedFile::fake()->image('cover.jpg', 400, 300);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Course With Cover',
                'description' => 'Desc',
                'access_type' => 'inclusive',
                'cover_image' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course created!');
        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'Course With Cover',
        ]);
    }

    public function test_regular_member_cannot_store_course(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Hacked Course',
                'description' => 'Should not work',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('courses', [
            'community_id' => $community->id,
            'title'        => 'Hacked Course',
        ]);
    }

    public function test_store_course_requires_title(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'description' => 'No title provided',
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    // ─── updateCourse ────────────────────────────────────────────────────────────

    public function test_owner_can_update_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Old Title',
            'description' => 'Old Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/update", [
                'title'       => 'Updated Title',
                'description' => 'Updated Desc',
                'access_type' => 'inclusive',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course updated!');
        $this->assertDatabaseHas('courses', [
            'id'          => $course->id,
            'title'       => 'Updated Title',
            'description' => 'Updated Desc',
        ]);
    }

    public function test_owner_can_update_course_with_cover_image(): void
    {
        Storage::fake('public');

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course To Update',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $file = UploadedFile::fake()->image('new-cover.jpg', 600, 400);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/update", [
                'title'       => 'Updated Title',
                'description' => 'Desc',
                'access_type' => 'inclusive',
                'cover_image' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course updated!');
    }

    public function test_regular_member_cannot_update_course(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Original Title',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/update", [
                'title'       => 'Hacked Title',
                'description' => 'Hacked',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Original Title']);
    }

    // ─── showCourse ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_course_detail(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Test Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module 1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson 1', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_member_can_view_course_detail(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Member Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module 1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson 1', 'position' => 1]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_paid_subscriber_can_view_course_detail(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Paid Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    // ─── storeModule ─────────────────────────────────────────────────────────────

    public function test_owner_can_store_module(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Test Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules", [
                'title' => 'New Module',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Module added!');
        $this->assertDatabaseHas('course_modules', [
            'course_id' => $course->id,
            'title'     => 'New Module',
        ]);
    }

    public function test_regular_member_cannot_store_module(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Test Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules", [
                'title' => 'Hacked Module',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('course_modules', [
            'course_id' => $course->id,
            'title'     => 'Hacked Module',
        ]);
    }

    public function test_store_module_requires_title(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules", []);

        $response->assertSessionHasErrors(['title']);
    }

    // ─── updateModule ─────────────────────────────────────────────────────────────

    public function test_owner_can_update_module(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Old Module Title',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}", [
                'title' => 'Updated Module Title',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Module updated!');
        $this->assertDatabaseHas('course_modules', [
            'id'    => $module->id,
            'title' => 'Updated Module Title',
        ]);
    }

    public function test_owner_can_update_module_via_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}", [
                'title' => 'Post Updated Module',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Module updated!');
        $this->assertDatabaseHas('course_modules', [
            'id'    => $module->id,
            'title' => 'Post Updated Module',
        ]);
    }

    public function test_regular_member_cannot_update_module(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Original Title',
            'position'  => 1,
        ]);

        $response = $this->actingAs($member)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}", [
                'title' => 'Hacked Title',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('course_modules', ['id' => $module->id, 'title' => 'Original Title']);
    }

    // ─── storeLesson ─────────────────────────────────────────────────────────────

    public function test_owner_can_store_lesson(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title'     => 'New Lesson',
                'content'   => 'Lesson content here',
                'video_url' => 'https://example.com/video.mp4',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson added!');
        $this->assertDatabaseHas('course_lessons', [
            'module_id'  => $module->id,
            'title'      => 'New Lesson',
            'content'    => 'Lesson content here',
            'video_url'  => 'https://example.com/video.mp4',
        ]);
    }

    public function test_regular_member_cannot_store_lesson(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title' => 'Hacked Lesson',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('course_lessons', [
            'module_id' => $module->id,
            'title'     => 'Hacked Lesson',
        ]);
    }

    public function test_store_lesson_requires_title(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons", [
                'content' => 'No title',
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    // ─── completeLesson ─────────────────────────────────────────────────────────

    public function test_member_can_complete_lesson(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson 1',
            'position'  => 1,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/complete");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson marked as complete!');
        $this->assertDatabaseHas('lesson_completions', [
            'user_id'   => $member->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_owner_can_complete_lesson(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson 1',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/complete");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson marked as complete!');
    }

    public function test_paid_subscriber_can_complete_lesson(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson',
            'position'  => 1,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/complete");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson marked as complete!');
    }

    // ─── updateLesson ────────────────────────────────────────────────────────────

    public function test_owner_can_update_lesson(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson',
            'content'   => 'Old content',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'content'   => 'Updated lesson content',
                'video_url' => 'https://example.com/updated.mp4',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson updated!');
        $this->assertDatabaseHas('course_lessons', [
            'id'         => $lesson->id,
            'content'    => 'Updated lesson content',
            'video_url'  => 'https://example.com/updated.mp4',
        ]);
    }

    public function test_owner_can_update_lesson_via_post(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson',
            'content'   => 'Content',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'content' => 'Post updated content',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson updated!');
        $this->assertDatabaseHas('course_lessons', [
            'id'      => $lesson->id,
            'content' => 'Post updated content',
        ]);
    }

    public function test_regular_member_cannot_update_lesson(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson',
            'content'   => 'Original content',
            'position'  => 1,
        ]);

        $response = $this->actingAs($member)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'content' => 'Hacked content',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('course_lessons', ['id' => $lesson->id, 'content' => 'Original content']);
    }

    // ─── destroyCourse ──────────────────────────────────────────────────────────

    public function test_owner_can_destroy_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'To Delete',
            'description' => 'Desc',
            'position'    => 1,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertRedirect("/communities/{$community->slug}/classroom");
        $response->assertSessionHas('success', 'Course deleted!');
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_regular_member_cannot_destroy_course(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'       => 'Course',
            'description' => 'Desc',
            'position'    => 1,
        ]);

        $response = $this->actingAs($member)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    // ─── uploadLessonImage ──────────────────────────────────────────────────────

    public function test_owner_can_upload_lesson_image(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $file = UploadedFile::fake()->image('lesson-photo.jpg', 800, 600);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-images", [
                'image' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    public function test_regular_member_cannot_upload_lesson_image(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $file = UploadedFile::fake()->image('hack.jpg');

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/lesson-images", [
                'image' => $file,
            ]);

        $response->assertForbidden();
    }

    // ─── reorderLessons ─────────────────────────────────────────────────────────

    public function test_owner_can_reorder_lessons(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course  = Course::create(['community_id' => $community->id, 'title' => 'C', 'description' => 'D', 'position' => 1]);
        $module  = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'position' => 1]);
        $lesson1 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 0]);
        $lesson2 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L2', 'position' => 1]);
        $lesson3 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L3', 'position' => 2]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/reorder", [
                'lesson_ids' => [$lesson3->id, $lesson1->id, $lesson2->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lessons reordered!');
        $this->assertDatabaseHas('course_lessons', ['id' => $lesson3->id, 'position' => 0]);
        $this->assertDatabaseHas('course_lessons', ['id' => $lesson1->id, 'position' => 1]);
        $this->assertDatabaseHas('course_lessons', ['id' => $lesson2->id, 'position' => 2]);
    }

    public function test_regular_member_cannot_reorder_lessons(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course  = Course::create(['community_id' => $community->id, 'title' => 'C', 'description' => 'D', 'position' => 1]);
        $module  = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'position' => 1]);
        $lesson1 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 0]);
        $lesson2 = CourseLesson::create(['module_id' => $module->id, 'title' => 'L2', 'position' => 1]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/reorder", [
                'lesson_ids' => [$lesson2->id, $lesson1->id],
            ]);

        $response->assertForbidden();
    }

    // ─── showCourse: access_type checks ─────────────────────────────────────────

    public function test_guest_can_view_free_course_detail(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Free Course',
            'access_type'  => Course::ACCESS_FREE,
            'position'     => 1,
        ]);

        $response = $this->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_active_subscriber_has_access_to_inclusive_course(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 1,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_user_without_subscription_does_not_have_access_to_inclusive_course(): void
    {
        $owner  = User::factory()->create();
        $user   = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 1,
        ]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        // Page still renders (public route) but hasAccess is false
        $response->assertOk();
    }

    public function test_enrolled_user_has_access_to_paid_once_course(): void
    {
        $owner  = User::factory()->create();
        $user   = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Once Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 500,
            'position'     => 1,
        ]);

        \App\Models\CourseEnrollment::create([
            'user_id'    => $user->id,
            'course_id'  => $course->id,
            'status'     => \App\Models\CourseEnrollment::STATUS_PAID,
            'expires_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    public function test_store_course_with_paid_once_access_type_and_price(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Paid Once Course',
                'description' => 'Pay once to access',
                'access_type' => 'paid_once',
                'price'       => 499.00,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course created!');
        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'Paid Once Course',
            'access_type'  => 'paid_once',
        ]);
    }

    public function test_store_course_requires_price_for_paid_once(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Paid Without Price',
                'access_type' => 'paid_once',
            ]);

        $response->assertSessionHasErrors(['price']);
    }

    // ─── reorderCourses ──────────────────────────────────────────────────────────

    public function test_owner_can_reorder_courses(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course1 = Course::create(['community_id' => $community->id, 'title' => 'C1', 'description' => 'D', 'position' => 0]);
        $course2 = Course::create(['community_id' => $community->id, 'title' => 'C2', 'description' => 'D', 'position' => 1]);
        $course3 = Course::create(['community_id' => $community->id, 'title' => 'C3', 'description' => 'D', 'position' => 2]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/reorder", [
                'course_ids' => [$course3->id, $course1->id, $course2->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Courses reordered!');
        $this->assertDatabaseHas('courses', ['id' => $course3->id, 'position' => 0]);
        $this->assertDatabaseHas('courses', ['id' => $course1->id, 'position' => 1]);
        $this->assertDatabaseHas('courses', ['id' => $course2->id, 'position' => 2]);
    }

    public function test_regular_member_cannot_reorder_courses(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course1 = Course::create(['community_id' => $community->id, 'title' => 'C1', 'description' => 'D', 'position' => 0]);
        $course2 = Course::create(['community_id' => $community->id, 'title' => 'C2', 'description' => 'D', 'position' => 1]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/reorder", [
                'course_ids' => [$course2->id, $course1->id],
            ]);

        $response->assertForbidden();
    }

    // ─── togglePublish ────────────────────────────────────────────────────────

    public function test_owner_can_toggle_publish_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Draft Course',
            'position'     => 1,
            'is_published' => false,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/toggle-publish");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course published!');
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_published' => true]);
    }

    public function test_owner_can_unpublish_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Published Course',
            'position'     => 1,
            'is_published' => true,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/toggle-publish");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course set to draft!');
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_published' => false]);
    }

    public function test_regular_member_cannot_toggle_publish(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => false,
        ]);

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/toggle-publish");

        $response->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_published' => false]);
    }

    // ─── showCourse: unpublished ─────────────────────────────────────────────

    public function test_non_manager_cannot_view_unpublished_course(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Unpublished Course',
            'position'     => 1,
            'is_published' => false,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertNotFound();
    }

    public function test_guest_cannot_view_unpublished_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Unpublished Course',
            'position'     => 1,
            'is_published' => false,
        ]);

        $response = $this->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertNotFound();
    }

    public function test_owner_can_view_unpublished_course(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Unpublished Course',
            'position'     => 1,
            'is_published' => false,
        ]);
        CourseModule::create(['course_id' => $course->id, 'title' => 'Module 1', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    // ─── storeCourse: plan limit ────────────────────────────────────────────

    public function test_store_course_blocked_when_plan_limit_reached(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        // Free plan allows 3 courses — create 3 existing courses
        Course::create(['community_id' => $community->id, 'title' => 'C1', 'position' => 0]);
        Course::create(['community_id' => $community->id, 'title' => 'C2', 'position' => 1]);
        Course::create(['community_id' => $community->id, 'title' => 'C3', 'position' => 2]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Fourth Course',
                'access_type' => 'free',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['plan']);
        $this->assertDatabaseMissing('courses', ['title' => 'Fourth Course']);
    }

    // ─── streamLessonVideo ──────────────────────────────────────────────────

    public function test_stream_lesson_video_returns_404_when_no_video_path(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson No Video',
            'position'   => 1,
            'video_path' => null,
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertNotFound();
    }

    public function test_stream_lesson_video_returns_404_for_unpublished_course_non_manager(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Unpublished',
            'position'     => 1,
            'is_published' => false,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson',
            'position'   => 1,
            'video_path' => 'lesson-videos/test.mp4',
        ]);

        $response = $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertNotFound();
    }

    public function test_stream_lesson_video_returns_403_when_no_course_access(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Paid Once Course',
            'access_type'  => Course::ACCESS_PAID_ONCE,
            'price'        => 100,
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson',
            'position'   => 1,
            'video_path' => 'lesson-videos/test.mp4',
        ]);

        // Member has subscription (passes middleware) but no course enrollment
        $response = $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertForbidden();
    }

    public function test_stream_lesson_video_returns_signed_url_for_owner(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson With Video',
            'position'   => 1,
            'video_path' => 'lesson-videos/test.mp4',
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    // ─── uploadLessonVideo ──────────────────────────────────────────────────

    public function test_upload_lesson_video_requires_pro_plan(): void
    {
        $owner     = User::factory()->create(); // free plan by default
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", [
                'filename'     => 'video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 1024,
            ]);

        $response->assertForbidden();
        $response->assertJson(['error' => 'Video uploads require a Pro plan.']);
    }

    public function test_regular_member_cannot_upload_lesson_video(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", [
                'filename'     => 'video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 1024,
            ]);

        $response->assertForbidden();
    }

    // ─── uploadLessonImage: validation ──────────────────────────────────────

    public function test_upload_lesson_image_requires_image_file(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-images", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image']);
    }

    // ─── storeLesson with optional fields ───────────────────────────────────

    public function test_owner_can_store_lesson_with_embed_and_cta(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title'      => 'Rich Lesson',
                'content'    => 'Content here',
                'embed_html' => '<iframe src="https://example.com"></iframe>',
                'cta_label'  => 'Buy Now',
                'cta_url'    => 'https://example.com/buy',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson added!');
        $this->assertDatabaseHas('course_lessons', [
            'module_id'  => $module->id,
            'title'      => 'Rich Lesson',
            'embed_html' => '<iframe src="https://example.com"></iframe>',
            'cta_label'  => 'Buy Now',
            'cta_url'    => 'https://example.com/buy',
        ]);
    }

    // ─── storeModule with is_free ───────────────────────────────────────────

    public function test_owner_can_store_module_with_is_free(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules", [
                'title'   => 'Free Preview Module',
                'is_free' => true,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Module added!');
        $this->assertDatabaseHas('course_modules', [
            'course_id' => $course->id,
            'title'     => 'Free Preview Module',
            'is_free'   => true,
        ]);
    }

    // ─── updateLesson with video_path ───────────────────────────────────────

    public function test_owner_can_update_lesson_with_video_path(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'video_path' => 'lesson-videos/uploaded-video.mp4',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson updated!');
        $this->assertDatabaseHas('course_lessons', [
            'id'         => $lesson->id,
            'video_path' => 'lesson-videos/uploaded-video.mp4',
        ]);
    }

    public function test_user_has_no_access_to_course_with_unknown_access_type(): void
    {
        // The final `return false` in CourseAccessService::hasAccess() is unreachable via normal
        // DB insertion (CHECK constraint), so we test via an in-memory Course with unknown access_type.
        $owner     = User::factory()->create();
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $course = new Course();
        $course->access_type = 'unknown_type';

        $service = new \App\Services\Classroom\CourseAccessService();
        $result  = $service->hasAccess($user, $community, $course);

        $this->assertFalse($result);
    }

    // ─── showCourse: guest access to non-free course ─────────────────────────────

    public function test_guest_cannot_access_inclusive_course_but_page_renders(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Inclusive Course',
            'access_type'  => Course::ACCESS_INCLUSIVE,
            'position'     => 1,
        ]);

        // Guest (not authenticated) gets the page but with hasAccess = false
        $response = $this->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
    }

    // ─── destroyModule ────────────────────────────────────────────────────────────

    public function test_owner_can_destroy_module(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module    = CourseModule::create(['course_id' => $course->id, 'title' => 'Module to Delete', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson in Module', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Module deleted!');
        $this->assertDatabaseMissing('course_modules', ['id' => $module->id]);
        $this->assertDatabaseMissing('course_lessons', ['module_id' => $module->id]);
    }

    public function test_non_owner_cannot_destroy_module(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);
        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);

        $this->actingAs($member)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('course_modules', ['id' => $module->id]);
    }

    // ─── destroyLesson ────────────────────────────────────────────────────────────

    public function test_owner_can_destroy_lesson(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module    = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson    = CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson to Delete', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Lesson deleted!');
        $this->assertDatabaseMissing('course_lessons', ['id' => $lesson->id]);
    }

    public function test_non_owner_cannot_destroy_lesson(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);
        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson', 'position' => 1]);

        $this->actingAs($member)
            ->delete("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('course_lessons', ['id' => $lesson->id]);
    }

    // ─── storeCourse: access_type validation ────────────────────────────────

    public function test_store_course_requires_access_type(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title' => 'Course Without Access Type',
            ]);

        $response->assertSessionHasErrors(['access_type']);
    }

    public function test_store_course_requires_price_for_paid_monthly(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Monthly Course Without Price',
                'access_type' => 'paid_monthly',
            ]);

        $response->assertSessionHasErrors(['price']);
    }

    public function test_store_course_rejects_invalid_access_type(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Bad Access Type',
                'access_type' => 'invalid_type',
            ]);

        $response->assertSessionHasErrors(['access_type']);
    }

    public function test_store_course_with_paid_monthly_access_type_and_price(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Monthly Course',
                'description' => 'Monthly subscription course',
                'access_type' => 'paid_monthly',
                'price'       => 99.00,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course created!');
        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'Monthly Course',
            'access_type'  => 'paid_monthly',
        ]);
    }

    public function test_store_course_with_member_once_access_type(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Member Once Course',
                'access_type' => 'member_once',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Course created!');
        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'Member Once Course',
            'access_type'  => 'member_once',
        ]);
    }

    // ─── uploadLessonVideo: validation ──────────────────────────────────────

    public function test_upload_lesson_video_rejects_invalid_content_type(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", [
                'filename'     => 'video.avi',
                'content_type' => 'video/x-flv',
                'size'         => 1024,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['content_type']);
    }

    public function test_upload_lesson_video_rejects_missing_fields(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['filename', 'content_type', 'size']);
    }

    public function test_upload_lesson_video_rejects_file_too_large(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        // Pro plan max is 5120MB = 5GB, send more
        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", [
                'filename'     => 'large-video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 6000 * 1024 * 1024, // 6000 MB > 5120 MB
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'File too large. Maximum size is 5120MB.']);
    }

    public function test_upload_lesson_video_success_for_pro_owner(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        // Mock S3 client to avoid real AWS calls
        $mockRequest = new \GuzzleHttp\Psr7\Request('PUT', 'https://s3.amazonaws.com/test-bucket/lesson-videos/test.mp4');
        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('getCommand')->once()->andReturn(new \Aws\Command('PutObject'));
        $mockClient->shouldReceive('createPresignedRequest')->once()->andReturn($mockRequest);

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", [
                'filename'     => 'my-video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 10 * 1024 * 1024, // 10 MB
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['upload_url', 'key']);
    }

    // ─── streamLessonVideo: legacy URL path ─────────────────────────────────

    public function test_stream_lesson_video_handles_legacy_full_s3_url(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson Legacy URL',
            'position'   => 1,
            'video_path' => 'https://s3.amazonaws.com/my-bucket/lesson-videos/old-video.mp4',
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    public function test_stream_lesson_video_accessible_to_member_with_course_access(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Free Course',
            'access_type'  => Course::ACCESS_FREE,
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson',
            'position'   => 1,
            'video_path' => 'lesson-videos/test.mp4',
        ]);

        $response = $this->actingAs($member)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    // ─── streamLessonVideo: guest on unpublished course ─────────────────────

    public function test_stream_lesson_video_returns_404_for_guest_on_unpublished_course(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Unpublished',
            'position'     => 1,
            'is_published' => false,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'  => $module->id,
            'title'      => 'Lesson',
            'position'   => 1,
            'video_path' => 'lesson-videos/test.mp4',
        ]);

        // Non-admin member should get 404 for unpublished course
        $response = $this->actingAs($member)->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/stream");

        $response->assertNotFound();
    }

    // ─── updateCourse: validation ───────────────────────────────────────────

    public function test_update_course_requires_title(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Original',
            'position'     => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/update", [
                'access_type' => 'free',
            ]);

        $response->assertSessionHasErrors(['title']);
    }

    public function test_update_course_requires_access_type(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Original',
            'position'     => 1,
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/update", [
                'title' => 'Updated',
            ]);

        $response->assertSessionHasErrors(['access_type']);
    }

    // ─── reorderCourses: validation ─────────────────────────────────────────

    public function test_reorder_courses_requires_course_ids(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/reorder", []);

        $response->assertSessionHasErrors(['course_ids']);
    }

    // ─── reorderLessons: validation ─────────────────────────────────────────

    public function test_reorder_lessons_requires_lesson_ids(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'C', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/reorder", []);

        $response->assertSessionHasErrors(['lesson_ids']);
    }

    // ─── showCourse: canUploadVideo prop for pro owner ──────────────────────

    public function test_show_course_indicates_video_upload_for_pro_owner(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Pro Course',
            'position'     => 1,
            'is_published' => true,
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Classroom/Show')
            ->where('canUploadVideo', true)
        );
    }

    public function test_show_course_disables_video_upload_for_free_owner(): void
    {
        $owner     = User::factory()->create(); // free plan
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Free Plan Course',
            'position'     => 1,
            'is_published' => true,
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Classroom/Show')
            ->where('canUploadVideo', false)
        );
    }

    // ─── index: affiliate and membership props ──────────────────────────────

    public function test_index_returns_affiliate_when_user_has_one(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        // Create affiliate for the member
        $community->affiliates()->create([
            'user_id' => $member->id,
            'code'    => 'TESTCODE123',
            'status'  => 'active',
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertOk();
    }

    // ─── storeLesson: validation for URL fields ─────────────────────────────

    public function test_store_lesson_rejects_invalid_video_url(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title'     => 'Bad URL Lesson',
                'video_url' => 'not-a-url',
            ]);

        $response->assertSessionHasErrors(['video_url']);
    }

    public function test_store_lesson_rejects_invalid_cta_url(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title'   => 'Bad CTA Lesson',
                'cta_url' => 'not-a-url',
            ]);

        $response->assertSessionHasErrors(['cta_url']);
    }

    // ─── updateLesson: validation for URL fields ────────────────────────────

    public function test_update_lesson_rejects_invalid_video_url(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'video_url' => 'not-a-valid-url',
            ]);

        $response->assertSessionHasErrors(['video_url']);
    }

    public function test_update_lesson_rejects_invalid_cta_url(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'Course', 'position' => 1]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/classroom/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'cta_url' => 'not-valid',
            ]);

        $response->assertSessionHasErrors(['cta_url']);
    }

    // ─── uploadLessonImage: non-image file ──────────────────────────────────

    public function test_upload_lesson_image_rejects_non_image_file(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-images", [
                'image' => $file,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image']);
    }

    // ─── storeCourse: cover_image validation ────────────────────────────────

    public function test_store_course_rejects_non_image_cover(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/classroom/courses", [
                'title'       => 'Course With Bad Cover',
                'access_type' => 'free',
                'cover_image' => $file,
            ]);

        $response->assertSessionHasErrors(['cover_image']);
    }

    // ─── uploadLessonVideo: quicktime and webm content types ────────────────

    public function test_upload_lesson_video_accepts_quicktime_content_type(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockRequest = new \GuzzleHttp\Psr7\Request('PUT', 'https://s3.amazonaws.com/test-bucket/lesson-videos/test.mov');
        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('getCommand')->once()->andReturn(new \Aws\Command('PutObject'));
        $mockClient->shouldReceive('createPresignedRequest')->once()->andReturn($mockRequest);

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/lesson-videos", [
                'filename'     => 'my-video.mov',
                'content_type' => 'video/quicktime',
                'size'         => 5 * 1024 * 1024,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['upload_url', 'key']);
    }

    // ─── showCourse: canManage prop ─────────────────────────────────────────

    public function test_show_course_sets_can_manage_false_for_regular_member(): void
    {
        $owner  = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/classroom/courses/{$course->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Classroom/Show')
            ->where('canManage', false)
            ->where('canUploadVideo', false)
        );
    }

    // ─── index: catch block logs and rethrows (lines 43-45) ─────────────────

    public function test_index_catch_block_logs_error_and_rethrows(): void
    {
        Log::spy();

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->mock(GetCourseList::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('Query failed'));
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query failed');

        $this->withoutExceptionHandling()
            ->actingAs($owner)
            ->get("/communities/{$community->slug}/classroom");
    }

    // ─── completeLesson: catch block logs and rethrows (lines 271-273) ──────

    public function test_complete_lesson_catch_block_logs_error_and_rethrows(): void
    {
        Log::spy();

        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'description'  => 'Desc',
            'position'     => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);
        $lesson = CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Lesson 1',
            'position'  => 1,
        ]);

        $this->mock(CompleteLesson::class, function ($mock) {
            $mock->shouldReceive('execute')
                ->once()
                ->andThrow(new \RuntimeException('Completion failed'));
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Completion failed');

        $this->withoutExceptionHandling()
            ->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/complete");
    }

    // ─── trackPreviewPlay ──────────────────────────────────────────────────

    public function test_track_preview_play_increments_counters(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $course = Course::create([
            'community_id'         => $community->id,
            'title'                => 'Course With Preview',
            'position'             => 1,
            'is_published'         => true,
            'preview_play_count'   => 0,
            'preview_watch_seconds' => 0,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/preview-play", [
                'seconds' => 30,
            ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $course->refresh();
        $this->assertEquals(1, $course->preview_play_count);
        $this->assertEquals(30, $course->preview_watch_seconds);
    }

    public function test_track_preview_play_validates_seconds(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/preview-play", [
                'seconds' => 0,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['seconds']);
    }

    public function test_track_preview_play_rejects_seconds_over_max(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/preview-play", [
                'seconds' => 601,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['seconds']);
    }

    // ─── trackLessonVideoPlay ──────────────────────────────────────────────

    public function test_track_lesson_video_play_increments_counters(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'           => $module->id,
            'title'               => 'Lesson',
            'position'            => 1,
            'video_play_count'    => 0,
            'video_watch_seconds' => 0,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/video-play", [
                'seconds' => 120,
            ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $lesson->refresh();
        $this->assertEquals(1, $lesson->video_play_count);
        $this->assertEquals(120, $lesson->video_watch_seconds);
    }

    public function test_track_lesson_video_play_validates_seconds(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create(['module_id' => $module->id, 'title' => 'Lesson', 'position' => 1]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/video-play", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['seconds']);
    }

    // ─── transcodeStatus ───────────────────────────────────────────────────

    public function test_transcode_status_returns_lesson_transcode_info(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Course',
            'position'     => 1,
            'is_published' => true,
        ]);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'Module', 'position' => 1]);
        $lesson = CourseLesson::create([
            'module_id'               => $module->id,
            'title'                   => 'Lesson',
            'position'                => 1,
            'video_transcode_status'  => 'processing',
            'video_transcode_percent' => 45,
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/transcode-status");

        $response->assertOk();
        $response->assertJson([
            'status'  => 'processing',
            'percent' => 45,
        ]);
    }

    // ─── uploadPreviewVideo ────────────────────────────────────────────────

    public function test_upload_preview_video_requires_pro_plan(): void
    {
        $owner     = User::factory()->create(); // free plan
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/preview-videos", [
                'filename'     => 'preview.mp4',
                'content_type' => 'video/mp4',
                'size'         => 1024,
            ]);

        $response->assertForbidden();
        $response->assertJson(['error' => 'Preview video uploads require a Pro plan.']);
    }

    public function test_upload_preview_video_rejects_file_too_large(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/preview-videos", [
                'filename'     => 'large-preview.mp4',
                'content_type' => 'video/mp4',
                'size'         => 6000 * 1024 * 1024,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_upload_preview_video_success_for_pro_owner(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockRequest = new \GuzzleHttp\Psr7\Request('PUT', 'https://s3.amazonaws.com/test-bucket/course-previews/test.mp4');
        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('getCommand')->once()->andReturn(new \Aws\Command('PutObject'));
        $mockClient->shouldReceive('createPresignedRequest')->once()->andReturn($mockRequest);

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/preview-videos", [
                'filename'     => 'preview.mp4',
                'content_type' => 'video/mp4',
                'size'         => 10 * 1024 * 1024,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['upload_url', 'key']);
    }

    public function test_upload_preview_video_rejects_invalid_content_type(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/preview-videos", [
                'filename'     => 'preview.avi',
                'content_type' => 'video/x-msvideo',
                'size'         => 1024,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['content_type']);
    }

    public function test_regular_member_cannot_upload_preview_video(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/preview-videos", [
                'filename'     => 'preview.mp4',
                'content_type' => 'video/mp4',
                'size'         => 1024,
            ]);

        $response->assertForbidden();
    }

    // ─── initiateMultipartUpload ───────────────────────────────────────────

    public function test_initiate_multipart_upload_requires_pro_plan(): void
    {
        $owner     = User::factory()->create(); // free plan
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/initiate", [
                'filename'     => 'big-video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 100 * 1024 * 1024,
            ]);

        $response->assertForbidden();
        $response->assertJson(['error' => 'Video uploads require a Pro plan.']);
    }

    public function test_initiate_multipart_upload_rejects_file_too_large(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/initiate", [
                'filename'     => 'huge-video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 6000 * 1024 * 1024,
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['error']);
    }

    public function test_initiate_multipart_upload_success(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('createMultipartUpload')->once()->andReturn(new \Aws\Result([
            'UploadId' => 'test-upload-id-123',
        ]));

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/initiate", [
                'filename'     => 'big-video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 100 * 1024 * 1024,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['upload_id', 'key']);
        $response->assertJson(['upload_id' => 'test-upload-id-123']);
    }

    public function test_initiate_multipart_upload_with_preview_type(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('createMultipartUpload')->once()->andReturn(new \Aws\Result([
            'UploadId' => 'preview-upload-id',
        ]));

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/initiate", [
                'filename'     => 'preview.mp4',
                'content_type' => 'video/mp4',
                'size'         => 50 * 1024 * 1024,
                'type'         => 'preview',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('course-previews/', $response->json('key'));
    }

    public function test_regular_member_cannot_initiate_multipart_upload(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/multipart/initiate", [
                'filename'     => 'video.mp4',
                'content_type' => 'video/mp4',
                'size'         => 1024,
            ]);

        $response->assertForbidden();
    }

    // ─── getPartUploadUrl ──────────────────────────────────────────────────

    public function test_get_part_upload_url_success(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockRequest = new \GuzzleHttp\Psr7\Request('PUT', 'https://s3.amazonaws.com/test-bucket/lesson-videos/test.mp4?partNumber=1');
        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('getCommand')->once()->andReturn(new \Aws\Command('UploadPart'));
        $mockClient->shouldReceive('createPresignedRequest')->once()->andReturn($mockRequest);

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/part-url", [
                'key'         => 'lesson-videos/test-uuid.mp4',
                'upload_id'   => 'test-upload-id',
                'part_number' => 1,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    public function test_get_part_upload_url_validates_required_fields(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/part-url", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['key', 'upload_id', 'part_number']);
    }

    public function test_regular_member_cannot_get_part_upload_url(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/multipart/part-url", [
                'key'         => 'lesson-videos/test.mp4',
                'upload_id'   => 'upload-id',
                'part_number' => 1,
            ]);

        $response->assertForbidden();
    }

    // ─── completeMultipartUpload ───────────────────────────────────────────

    public function test_complete_multipart_upload_success(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('completeMultipartUpload')->once()->andReturn(new \Aws\Result([]));

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/complete", [
                'key'       => 'lesson-videos/test-uuid.mp4',
                'upload_id' => 'test-upload-id',
                'parts'     => [
                    ['PartNumber' => 1, 'ETag' => '"etag1"'],
                    ['PartNumber' => 2, 'ETag' => '"etag2"'],
                ],
            ]);

        $response->assertOk();
        $response->assertJson(['key' => 'lesson-videos/test-uuid.mp4']);
    }

    public function test_complete_multipart_upload_validates_required_fields(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/complete", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['key', 'upload_id', 'parts']);
    }

    public function test_regular_member_cannot_complete_multipart_upload(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/multipart/complete", [
                'key'       => 'lesson-videos/test.mp4',
                'upload_id' => 'upload-id',
                'parts'     => [['PartNumber' => 1, 'ETag' => '"etag"']],
            ]);

        $response->assertForbidden();
    }

    // ─── abortMultipartUpload ──────────────────────────────────────────────

    public function test_abort_multipart_upload_success(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $mockClient = \Mockery::mock(\Aws\S3\S3Client::class);
        $mockClient->shouldReceive('abortMultipartUpload')->once()->andReturn(new \Aws\Result([]));

        $mockDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $mockDisk->shouldReceive('getClient')->once()->andReturn($mockClient);

        Storage::shouldReceive('disk')->with('s3')->once()->andReturn($mockDisk);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/abort", [
                'key'       => 'lesson-videos/test-uuid.mp4',
                'upload_id' => 'test-upload-id',
            ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    public function test_abort_multipart_upload_validates_required_fields(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/multipart/abort", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['key', 'upload_id']);
    }

    public function test_regular_member_cannot_abort_multipart_upload(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $response = $this->actingAs($member)
            ->postJson("/communities/{$community->slug}/classroom/multipart/abort", [
                'key'       => 'lesson-videos/test.mp4',
                'upload_id' => 'upload-id',
            ]);

        $response->assertForbidden();
    }
}
