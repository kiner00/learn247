<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\DeletePost;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletePostTest extends TestCase
{
    use RefreshDatabase;

    private DeletePost $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new DeletePost;
    }

    public function test_author_can_delete_own_post(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->action->execute($user, $post);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_moderator_can_delete_any_post(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $mod = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->moderator()->create(['community_id' => $community->id, 'user_id' => $mod->id]);

        $this->action->execute($mod, $post);

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_regular_member_cannot_delete_others_post(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($other, $post);
    }

    public function test_non_member_cannot_delete_post(): void
    {
        $community = Community::factory()->create();
        $author = User::factory()->create();
        $outsider = User::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id, 'user_id' => $author->id]);

        $this->expectException(AuthorizationException::class);
        $this->action->execute($outsider, $post);
    }
}
