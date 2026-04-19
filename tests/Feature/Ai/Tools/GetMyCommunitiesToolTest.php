<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetMyCommunitiesTool;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetMyCommunitiesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_not_member_message_when_user_has_no_communities(): void
    {
        $user = User::factory()->create();
        $tool = new GetMyCommunitiesTool($user->id);

        $result = $tool->handle(new Request([]));

        $this->assertStringContainsString('not a member of any community', $result);
    }

    public function test_returns_user_communities_with_details(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create([
            'name' => 'Laravel Hub',
            'category' => 'programming',
            'price' => 299,
            'billing_type' => 'monthly',
        ]);

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
            'points' => 100,
            'joined_at' => now()->subDays(30),
            'expires_at' => now()->addDays(30),
        ]);

        $tool = new GetMyCommunitiesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertCount(1, $json);
        $this->assertSame('Laravel Hub', $json[0]['community']);
        $this->assertSame($community->slug, $json[0]['slug']);
        $this->assertSame('programming', $json[0]['category']);
        $this->assertSame('member', $json[0]['role']);
        $this->assertSame('paid', $json[0]['membership_type']);
        $this->assertSame(100, $json[0]['points']);
        $this->assertNotNull($json[0]['expires_at']);
        $this->assertNotNull($json[0]['joined_at']);
    }

    public function test_returns_multiple_communities(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $community = Community::factory()->create();
            CommunityMember::factory()->create([
                'community_id' => $community->id,
                'user_id' => $user->id,
            ]);
        }

        $tool = new GetMyCommunitiesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertCount(3, $json);
    }

    public function test_includes_level_based_on_points(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'points' => 0,
        ]);

        $tool = new GetMyCommunitiesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertSame(1, $json[0]['level']);
    }

    public function test_handles_null_expires_at(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'expires_at' => null,
        ]);

        $tool = new GetMyCommunitiesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertNull($json[0]['expires_at']);
    }

    public function test_shows_admin_role(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $tool = new GetMyCommunitiesTool($user->id);
        $result = $tool->handle(new Request([]));
        $json = json_decode($result, true);

        $this->assertSame('admin', $json[0]['role']);
    }

    public function test_description_returns_string(): void
    {
        $tool = new GetMyCommunitiesTool(1);
        $this->assertIsString($tool->description());
    }

    public function test_schema_returns_empty_array(): void
    {
        $tool = new GetMyCommunitiesTool(1);
        $schema = $this->createMock(\Illuminate\Contracts\JsonSchema\JsonSchema::class);

        $this->assertSame([], $tool->schema($schema));
    }
}
