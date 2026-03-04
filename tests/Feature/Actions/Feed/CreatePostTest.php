<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\CreatePost;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePostTest extends TestCase
{
    use RefreshDatabase;

    private CreatePost $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CreatePost();
    }

    public function test_member_can_create_post(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $post = $this->action->execute($user, $community, ['content' => 'Hello world!', 'title' => 'My Post']);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertDatabaseHas('posts', ['content' => 'Hello world!', 'community_id' => $community->id]);
    }

    public function test_post_without_title_is_allowed(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $post = $this->action->execute($user, $community, ['content' => 'No title']);

        $this->assertNull($post->title);
    }

    public function test_non_member_cannot_create_post(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->action->execute($user, $community, ['content' => 'Should fail']);
    }
}
