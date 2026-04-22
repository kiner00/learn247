<?php

namespace Tests\Feature\Api;

use App\Actions\Classroom\CreateLessonComment;
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

    private function createClassroomStructure(User $owner): array
    {
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $module = CourseModule::factory()->create(['course_id' => $course->id]);
        $lesson = CourseLesson::factory()->create(['module_id' => $module->id]);

        return [$community, $course, $lesson];
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $this->postJson("/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/comments", [
            'content' => 'Great lesson!',
        ])->assertUnauthorized();
    }

    public function test_store_returns_403_for_non_members(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => 'Great lesson!',
            ])
            ->assertForbidden();
    }

    public function test_store_validates_content_is_required(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/comments", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_store_validates_content_max_length(): void
    {
        $owner = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => str_repeat('x', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_store_creates_comment_and_returns_201(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $response = $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => 'This was very helpful!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Comment posted.')
            ->assertJsonStructure([
                'comment' => ['id', 'content', 'created_at', 'user' => ['id', 'name', 'username']],
            ]);

        $this->assertDatabaseHas('comments', [
            'lesson_id' => $lesson->id,
            'community_id' => $community->id,
            'user_id' => $member->id,
            'content' => 'This was very helpful!',
        ]);
    }

    public function test_store_returns_500_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        [$community, $course, $lesson] = $this->createClassroomStructure($owner);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $mock = $this->mock(CreateLessonComment::class);
        $mock->shouldReceive('execute')->once()->andThrow(new \RuntimeException('DB error'));

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/communities/{$community->slug}/courses/{$course->id}/lessons/{$lesson->id}/comments", [
                'content' => 'This will fail',
            ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to post comment.');
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_requires_authentication(): void
    {
        $comment = Comment::create([
            'lesson_id' => CourseLesson::factory()->create()->id,
            'community_id' => Community::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
            'content' => 'Test comment',
        ]);

        $this->deleteJson("/api/v1/lesson-comments/{$comment->id}")
            ->assertUnauthorized();
    }

    public function test_destroy_deletes_own_comment(): void
    {
        $user = User::factory()->create();
        $comment = Comment::create([
            'lesson_id' => CourseLesson::factory()->create()->id,
            'community_id' => Community::factory()->create()->id,
            'user_id' => $user->id,
            'content' => 'My comment',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/lesson-comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('deleted', $comment->id);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_destroy_returns_403_for_other_users_comment(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $comment = Comment::create([
            'lesson_id' => CourseLesson::factory()->create()->id,
            'community_id' => Community::factory()->create()->id,
            'user_id' => $author->id,
            'content' => 'Author comment',
        ]);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/lesson-comments/{$comment->id}")
            ->assertForbidden();
    }

    public function test_destroy_allows_super_admin_to_delete_any_comment(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $author = User::factory()->create();
        $comment = Comment::create([
            'lesson_id' => CourseLesson::factory()->create()->id,
            'community_id' => Community::factory()->create()->id,
            'user_id' => $author->id,
            'content' => 'Some comment',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/lesson-comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('deleted', $comment->id);
    }

    public function test_destroy_returns_500_when_delete_throws(): void
    {
        $user = User::factory()->create();
        $comment = Comment::create([
            'lesson_id' => CourseLesson::factory()->create()->id,
            'community_id' => Community::factory()->create()->id,
            'user_id' => $user->id,
            'content' => 'Will fail to delete',
        ]);

        // Force an exception by hooking into the deleting event
        Comment::deleting(function () {
            throw new \RuntimeException('Forced DB error');
        });

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/lesson-comments/{$comment->id}")
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to delete comment.');
    }
}
