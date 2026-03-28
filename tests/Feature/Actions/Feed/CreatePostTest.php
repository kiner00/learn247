<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\CreatePost;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreatePostTest extends TestCase
{
    use RefreshDatabase;

    private CreatePost $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = app(CreatePost::class);
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

    public function test_post_with_image_stores_file(): void
    {
        Storage::fake(config('filesystems.default'));
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $post = $this->action->execute($user, $community, [
            'content' => 'Post with image',
            'image'   => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $this->assertNotNull($post->image);
    }

    public function test_post_notifies_community_owner(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $this->action->execute($member, $community, ['content' => 'New post']);

        $this->assertDatabaseHas('notifications', [
            'user_id'  => $owner->id,
            'actor_id' => $member->id,
            'type'     => 'new_post',
        ]);
    }

    public function test_owner_posting_does_not_self_notify(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->action->execute($owner, $community, ['content' => 'Owner post']);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $owner->id,
            'type'    => 'new_post',
        ]);
    }
}
