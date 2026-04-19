<?php

namespace Tests\Feature\Web;

use App\Models\ChatbotMessage;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunitySettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCommunity(): array
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);

        return [$owner, $community];
    }

    // ─── Owner can access settings pages ────────────────────────────────────────

    public function test_owner_can_view_general_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/general");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/General')
            ->has('community')
            ->has('isPro')
            ->has('pricingGate')
        );
    }

    public function test_owner_can_view_affiliate_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/affiliate");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Affiliate')
        );
    }

    public function test_owner_can_view_ai_tools_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/ai-tools");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/AiTools')
        );
    }

    public function test_owner_can_view_domain_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/domain");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Domain')
            ->has('baseDomain')
            ->has('serverIp')
        );
    }

    public function test_owner_can_view_tags_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/tags");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Tags')
            ->has('tags')
        );
    }

    public function test_owner_can_view_announcements_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/announcements");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Announcements')
            ->has('community')
            ->has('isPro')
        );
    }

    public function test_owner_can_view_level_perks_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        // Create some level perks
        CommunityLevelPerk::create([
            'community_id' => $community->id,
            'level' => 1,
            'description' => 'Bronze badge',
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/level-perks");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/LevelPerks')
            ->has('levelPerks')
            ->has('community')
        );
    }

    public function test_owner_can_view_invite_members_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/invite-members");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/InviteMembers')
            ->has('community')
        );
    }

    public function test_owner_can_view_integrations_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/integrations");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Integrations')
            ->has('community')
        );
    }

    public function test_owner_can_view_sms_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/sms");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Sms')
            ->has('community')
        );
    }

    public function test_owner_can_view_email_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/email");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Email')
            ->has('community')
            ->has('hasApiKey')
            ->has('emailProvider')
            ->has('fromEmail')
            ->has('fromName')
            ->has('replyTo')
            ->has('domainId')
            ->has('domainStatus')
            ->has('providers')
        );
    }

    public function test_owner_can_view_workflows_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/workflows");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/Workflows')
            ->has('community')
        );
    }

    public function test_owner_can_view_danger_zone_settings(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/danger-zone");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/DangerZone')
            ->has('community')
        );
    }

    public function test_owner_can_view_chat_history(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        // Create a chatbot message so there's data to display
        ChatbotMessage::create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
            'role' => 'user',
            'content' => 'Hello bot',
            'conversation_id' => 'conv-1',
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/chat-history");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/ChatHistory')
            ->has('chatUsers')
            ->has('community')
        );
    }

    public function test_owner_can_view_chat_history_for_specific_user(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        ChatbotMessage::create([
            'community_id' => $community->id,
            'user_id' => $member->id,
            'role' => 'user',
            'content' => 'Hello from member',
            'conversation_id' => 'conv-2',
        ]);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/chat-history/{$member->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/ChatHistory')
            ->has('chatUsers')
            ->has('selectedUser')
            ->has('chatMessages')
        );
    }

    public function test_chat_history_empty_state(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/chat-history");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Settings/ChatHistory')
            ->where('chatUsers', [])
        );
    }

    // ─── Non-owner denied ───────────────────────────────────────────────────────

    public function test_regular_member_cannot_view_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $owner->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/settings/general");

        $response->assertForbidden();
    }

    public function test_non_member_is_redirected_from_settings(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($outsider)
            ->get("/communities/{$community->slug}/settings/general");

        // EnsureActiveMembership middleware redirects non-members
        $response->assertRedirect("/communities/{$community->slug}/about");
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->get("/communities/{$community->slug}/settings/general");

        $response->assertRedirect('/login');
    }
}
