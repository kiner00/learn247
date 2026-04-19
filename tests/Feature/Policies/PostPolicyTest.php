<?php

namespace Tests\Feature\Policies;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PostPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PostPolicy;
    }

    public function test_author_can_delete_own_post(): void
    {
        $post = Post::factory()->create();
        $author = User::find($post->user_id);

        $this->assertTrue($this->policy->delete($author, $post));
    }

    public function test_community_admin_can_delete_any_post(): void
    {
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $admin = User::factory()->create();
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($this->policy->delete($admin, $post));
    }

    public function test_community_moderator_can_delete_any_post(): void
    {
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $moderator = User::factory()->create();
        CommunityMember::factory()->moderator()->create([
            'community_id' => $community->id,
            'user_id' => $moderator->id,
        ]);

        $this->assertTrue($this->policy->delete($moderator, $post));
    }

    public function test_random_user_cannot_delete_post(): void
    {
        $post = Post::factory()->create();
        $random = User::factory()->create();

        $this->assertFalse($this->policy->delete($random, $post));
    }

    public function test_regular_member_cannot_delete_others_post(): void
    {
        $community = Community::factory()->create();
        $post = Post::factory()->create(['community_id' => $community->id]);

        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $this->assertFalse($this->policy->delete($member, $post));
    }
}
