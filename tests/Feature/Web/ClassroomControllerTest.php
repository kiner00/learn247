<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_non_member_cannot_view_classroom_index_on_free_community(): void
    {
        $owner     = User::factory()->create();
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_non_subscriber_cannot_view_classroom_index_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/classroom");

        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->get("/communities/{$community->slug}/classroom");

        $response->assertRedirect('/login');
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
        Storage::fake('public');

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
        Storage::disk('public')->assertExists('lesson-images/' . $file->hashName());
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
}
