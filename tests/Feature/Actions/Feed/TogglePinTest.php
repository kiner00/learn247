<?php

namespace Tests\Feature\Actions\Feed;

use App\Actions\Feed\TogglePin;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TogglePinTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_pin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $post = Post::factory()->create(['community_id' => $community->id, 'is_pinned' => false]);

        $action = new TogglePin;
        $result = $action->execute($owner, $post);

        $this->assertTrue($result->is_pinned);
    }

    public function test_owner_can_unpin_post(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $post = Post::factory()->create(['community_id' => $community->id, 'is_pinned' => true]);

        $action = new TogglePin;
        $result = $action->execute($owner, $post);

        $this->assertFalse($result->is_pinned);
    }

    public function test_admin_can_pin_post(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);
        $post = Post::factory()->create(['community_id' => $community->id, 'is_pinned' => false]);

        $action = new TogglePin;
        $result = $action->execute($admin, $post);

        $this->assertTrue($result->is_pinned);
    }

    public function test_regular_member_cannot_pin_post(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);
        $post = Post::factory()->create(['community_id' => $community->id]);

        $action = new TogglePin;

        $this->expectException(AuthorizationException::class);
        $action->execute($member, $post);
    }
}
