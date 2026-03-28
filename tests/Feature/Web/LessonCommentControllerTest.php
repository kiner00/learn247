<?php

namespace Tests\Feature\Web;

use App\Actions\Classroom\CreateLessonComment;
use App\Http\Middleware\EnsureActiveMembership;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createClassroomStructure(Community $community): CourseLesson
    {
        $course = Course::create([
            'community_id' => $community->id,
            'title'        => 'Test Course',
            'description'  => 'A test course',
            'position'     => 1,
        ]);
        $module = CourseModule::create([
            'course_id' => $course->id,
            'title'     => 'Test Module',
            'position'  => 1,
        ]);

        return CourseLesson::create([
            'module_id' => $module->id,
            'title'     => 'Test Lesson',
            'content'   => 'Lesson content',
            'position'  => 1,
        ]);
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_member_can_store_lesson_comment(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $lesson = $this->createClassroomStructure($community);
        $course = $lesson->module->course;

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => 'Great lesson!',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'lesson_id'    => $lesson->id,
            'user_id'      => $member->id,
            'community_id' => $community->id,
            'content'      => 'Great lesson!',
        ]);
    }

    public function test_store_requires_content(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $lesson = $this->createClassroomStructure($community);
        $course = $lesson->module->course;

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => '',
            ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_non_member_is_redirected_from_store(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $stranger  = User::factory()->create();

        $lesson = $this->createClassroomStructure($community);
        $course = $lesson->module->course;

        $response = $this->actingAs($stranger)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => 'Should not work',
            ]);

        $response->assertRedirect(route('communities.about', $community->slug));
    }

    public function test_guest_is_redirected_from_store(): void
    {
        $community = Community::factory()->create();
        $lesson    = $this->createClassroomStructure($community);
        $course    = $lesson->module->course;

        $response = $this->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/comments", [
            'content' => 'Anonymous comment',
        ]);

        $response->assertRedirect('/login');
    }

    // ─── destroy (bypasses EnsureActiveMembership — route lacks {community}) ─

    public function test_author_can_delete_own_comment(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $lesson  = $this->createClassroomStructure($community);
        $comment = Comment::create([
            'lesson_id'    => $lesson->id,
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'To be deleted',
        ]);

        $response = $this->withoutMiddleware(EnsureActiveMembership::class)
            ->actingAs($member)
            ->delete("/lesson-comments/{$comment->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_super_admin_can_delete_any_comment(): void
    {
        $admin     = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create();
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $lesson  = $this->createClassroomStructure($community);
        $comment = Comment::create([
            'lesson_id'    => $lesson->id,
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Admin will delete this',
        ]);

        $response = $this->withoutMiddleware(EnsureActiveMembership::class)
            ->actingAs($admin)
            ->delete("/lesson-comments/{$comment->id}");

        $response->assertRedirect();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_store_returns_error_when_action_throws(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $lesson = $this->createClassroomStructure($community);
        $course = $lesson->module->course;

        $mock = $this->mock(CreateLessonComment::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('DB error'));

        $response = $this->actingAs($member)
            ->post("/communities/{$community->slug}/classroom/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => 'This will fail',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to post comment.');
    }

    public function test_destroy_returns_error_when_delete_throws(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $lesson  = $this->createClassroomStructure($community);
        $comment = Comment::create([
            'lesson_id'    => $lesson->id,
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'content'      => 'Will fail to delete',
        ]);

        Comment::deleting(function () {
            throw new \RuntimeException('Forced DB error');
        });

        $response = $this->withoutMiddleware(EnsureActiveMembership::class)
            ->actingAs($member)
            ->delete("/lesson-comments/{$comment->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to delete comment.');
    }

    public function test_other_member_cannot_delete_comment(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $author    = User::factory()->create();
        $other     = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $lesson  = $this->createClassroomStructure($community);
        $comment = Comment::create([
            'lesson_id'    => $lesson->id,
            'community_id' => $community->id,
            'user_id'      => $author->id,
            'content'      => 'Cannot be deleted by other',
        ]);

        $response = $this->withoutMiddleware(EnsureActiveMembership::class)
            ->actingAs($other)
            ->delete("/lesson-comments/{$comment->id}");

        $response->assertForbidden();
    }
}
