<?php

namespace Tests\Feature\Policies;

use App\Models\Comment;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use App\Policies\CommentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentPolicyTest extends TestCase
{
    use RefreshDatabase;

    private CommentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CommentPolicy;
    }

    public function test_author_can_delete_own_comment(): void
    {
        $comment = Comment::factory()->create();
        $author = User::find($comment->user_id);

        $this->assertTrue($this->policy->delete($author, $comment));
    }

    public function test_community_admin_can_delete_any_comment(): void
    {
        $community = Community::factory()->create();
        $comment = Comment::factory()->create(['community_id' => $community->id]);

        $admin = User::factory()->create();
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $admin->id,
        ]);

        $this->assertTrue($this->policy->delete($admin, $comment));
    }

    public function test_community_moderator_can_delete_any_comment(): void
    {
        $community = Community::factory()->create();
        $comment = Comment::factory()->create(['community_id' => $community->id]);

        $moderator = User::factory()->create();
        CommunityMember::factory()->moderator()->create([
            'community_id' => $community->id,
            'user_id' => $moderator->id,
        ]);

        $this->assertTrue($this->policy->delete($moderator, $comment));
    }

    public function test_random_user_cannot_delete_comment(): void
    {
        $comment = Comment::factory()->create();
        $random = User::factory()->create();

        $this->assertFalse($this->policy->delete($random, $comment));
    }

    public function test_regular_member_cannot_delete_others_comment(): void
    {
        $community = Community::factory()->create();
        $comment = Comment::factory()->create(['community_id' => $community->id]);

        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $this->assertFalse($this->policy->delete($member, $comment));
    }
}
