<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\CreateComment;
use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateCommentTest extends TestCase
{
    use RefreshDatabase;

    private CreateComment $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateComment;
    }

    public function test_member_can_comment_on_post(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $comment = $this->action->execute($user, $post, ['content' => 'Great post!']);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertEquals($post->id, $comment->post_id);
        $this->assertEquals($community->id, $comment->community_id);
    }

    public function test_non_member_cannot_comment(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($user, $post, ['content' => 'Should fail']);
    }

    public function test_member_can_create_reply_to_comment(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        $parent = Comment::factory()->create(['post_id' => $post->id, 'community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $reply = $this->action->execute($user, $post, ['content' => 'Reply!', 'parent_id' => $parent->id]);

        $this->assertInstanceOf(Comment::class, $reply);
        $this->assertEquals($parent->id, $reply->parent_id);
        $this->assertEquals($post->id, $reply->post_id);
    }

    public function test_comment_without_parent_id_is_top_level(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $comment = $this->action->execute($user, $post, ['content' => 'Top level']);

        $this->assertNull($comment->parent_id);
    }
}
