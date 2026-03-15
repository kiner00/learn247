<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_courses(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Course::create([
            'community_id' => $community->id,
            'title'       => 'Test Course',
            'description' => 'Desc',
        ]);

        $this->actingAs($member)
            ->getJson("/api/communities/{$community->slug}/courses")
            ->assertOk()
            ->assertJsonStructure(['courses']);
    }

    public function test_non_member_gets_403_when_listing_courses(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/courses")
            ->assertForbidden();
    }

    public function test_owner_can_create_course_returns_201(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/api/communities/{$community->slug}/courses", [
                'title'       => 'New Course',
                'description' => 'Course description',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Course created.')
            ->assertJsonStructure(['course_id']);

        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'New Course',
            'description'  => 'Course description',
        ]);
    }

    public function test_non_owner_gets_403_when_creating_course(): void
    {
        $owner     = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->postJson("/api/communities/{$community->slug}/courses", [
                'title'       => 'New Course',
                'description' => 'Course description',
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_returns_401_when_listing_courses(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/api/communities/{$community->slug}/courses")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_when_creating_course(): void
    {
        $community = Community::factory()->create();

        $this->postJson("/api/communities/{$community->slug}/courses", [
            'title' => 'New Course',
        ])
            ->assertUnauthorized();
    }

    public function test_owner_can_store_module(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test',
            'description'  => 'Desc',
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/communities/{$community->slug}/courses/{$course->id}/modules", [
                'title' => 'New Module',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Module added.')
            ->assertJsonStructure(['module_id']);

        $this->assertDatabaseHas('course_modules', [
            'course_id' => $course->id,
            'title'     => 'New Module',
        ]);
    }

    public function test_owner_can_update_module(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test',
            'description'  => 'Desc',
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);

        $this->actingAs($owner)
            ->patchJson("/api/communities/{$community->slug}/courses/{$course->id}/modules/{$module->id}", [
                'title' => 'Updated Module',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Module updated.');

        $this->assertDatabaseHas('course_modules', [
            'id'    => $module->id,
            'title' => 'Updated Module',
        ]);
    }

    public function test_owner_can_store_lesson(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test',
            'description'  => 'Desc',
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Module 1',
            'position'  => 1,
        ]);

        $response = $this->actingAs($owner)
            ->postJson("/api/communities/{$community->slug}/courses/{$course->id}/modules/{$module->id}/lessons", [
                'title' => 'New Lesson',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Lesson added.')
            ->assertJsonStructure(['lesson_id']);

        $this->assertDatabaseHas('course_lessons', [
            'module_id' => $module->id,
            'title'     => 'New Lesson',
        ]);
    }

    public function test_owner_can_update_lesson(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course    = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test',
            'description'  => 'Desc',
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

        $this->actingAs($owner)
            ->patchJson("/api/communities/{$community->slug}/courses/{$course->id}/modules/{$module->id}/lessons/{$lesson->id}", [
                'content' => 'Updated lesson content',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Lesson updated.');

        $this->assertDatabaseHas('course_lessons', [
            'id'      => $lesson->id,
            'content' => 'Updated lesson content',
        ]);
    }

    public function test_member_can_complete_lesson(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test',
            'description'  => 'Desc',
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

        $this->actingAs($member)
            ->postJson("/api/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('message', 'Lesson marked as complete!');

        $this->assertDatabaseHas('lesson_completions', [
            'user_id'   => $member->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_non_owner_cannot_store_module(): void
    {
        $owner     = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $otherUser->id]);

        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test',
            'description'  => 'Desc',
        ]);

        $this->actingAs($otherUser)
            ->postJson("/api/communities/{$community->slug}/courses/{$course->id}/modules", [
                'title' => 'New Module',
            ])
            ->assertForbidden();
    }
}
