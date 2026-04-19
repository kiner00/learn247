<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\DeleteComment;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteCommentTest extends TestCase
{
    use RefreshDatabase;

    private DeleteComment $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeleteComment;
    }

    public function test_author_can_delete_own_comment(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id, 'user_id' => $user->id]);

        $this->action->execute($user, $comment);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_moderator_can_delete_any_comment(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $mod = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $mod->id]);

        $this->action->execute($mod, $comment);

        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }

    public function test_regular_member_cannot_delete_others_comment(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($other, $comment);
    }

    public function test_non_member_cannot_delete_comment(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $comment = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id, 'user_id' => $author->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($outsider, $comment);
    }
}
