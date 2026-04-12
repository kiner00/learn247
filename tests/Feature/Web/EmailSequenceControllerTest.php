<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\EmailCampaign;
use App\Models\EmailSequence;
use App\Models\EmailSequenceStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailSequenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCommunity(array $extra = []): array
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'price'    => 0,
        ], $extra));
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        return [$owner, $community];
    }

    private function createSequence(Community $community, string $status = 'draft'): EmailSequence
    {
        $campaign = EmailCampaign::create([
            'community_id' => $community->id,
            'name'         => 'Test Campaign',
            'type'         => EmailCampaign::TYPE_SEQUENCE,
            'status'       => EmailCampaign::STATUS_DRAFT,
        ]);

        return EmailSequence::create([
            'campaign_id'   => $campaign->id,
            'community_id'  => $community->id,
            'trigger_event' => EmailSequence::TRIGGER_MEMBER_JOINED,
            'status'        => $status,
        ]);
    }

    // ─── index ──────────────────────────────────────────────────────────────────

    public function test_owner_can_list_sequences(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $this->createSequence($community);

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/email-sequences");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Email/Sequences')
            ->has('sequences', 1)
        );
    }

    // ─── create ─────────────────────────────────────────────────────────────────

    public function test_owner_can_view_create_form(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $response = $this->actingAs($owner)
            ->get("/communities/{$community->slug}/email-sequences/create");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Email/SequenceCreate')
            ->has('triggers')
        );
    }

    // ─── store ──────────────────────────────────────────────────────────────────

    public function test_owner_can_store_sequence(): void
    {
        [$owner, $community] = $this->ownerWithCommunity([
            'resend_api_key'    => 'test-key',
            'resend_from_email' => 'hello@example.com',
            'resend_from_name'  => 'Test',
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/email-sequences", [
                'name'          => 'Welcome Series',
                'trigger_event' => EmailSequence::TRIGGER_MEMBER_JOINED,
                'steps'         => [
                    [
                        'subject'     => 'Welcome!',
                        'html_body'   => '<p>Hello there</p>',
                        'delay_hours' => 0,
                    ],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('email_sequences', [
            'community_id'  => $community->id,
            'trigger_event' => EmailSequence::TRIGGER_MEMBER_JOINED,
        ]);
        $this->assertDatabaseHas('email_sequence_steps', [
            'subject' => 'Welcome!',
        ]);
    }

    public function test_store_fails_without_resend_key(): void
    {
        [$owner, $community] = $this->ownerWithCommunity(['resend_api_key' => null]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/email-sequences", [
                'name'          => 'Welcome',
                'trigger_event' => EmailSequence::TRIGGER_MEMBER_JOINED,
                'steps'         => [
                    ['subject' => 'Hi', 'html_body' => '<p>Hi</p>', 'delay_hours' => 0],
                ],
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('resend');
    }

    // ─── activate / pause ───────────────────────────────────────────────────────

    public function test_owner_can_activate_sequence_with_steps(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $sequence = $this->createSequence($community);

        EmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'position'    => 1,
            'delay_hours' => 0,
            'subject'     => 'Step 1',
            'html_body'   => '<p>Body</p>',
            'from_email'  => 'test@example.com',
            'from_name'   => 'Test',
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/email-sequences/{$sequence->id}/activate");

        $response->assertRedirect();
        $this->assertDatabaseHas('email_sequences', [
            'id'     => $sequence->id,
            'status' => EmailSequence::STATUS_ACTIVE,
        ]);
    }

    public function test_activate_fails_without_steps(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $sequence = $this->createSequence($community);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/email-sequences/{$sequence->id}/activate");

        $response->assertRedirect();
        $response->assertSessionHasErrors('sequence');
    }

    public function test_owner_can_pause_sequence(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $sequence = $this->createSequence($community, EmailSequence::STATUS_ACTIVE);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/email-sequences/{$sequence->id}/pause");

        $response->assertRedirect();
        $this->assertDatabaseHas('email_sequences', [
            'id'     => $sequence->id,
            'status' => EmailSequence::STATUS_PAUSED,
        ]);
    }

    // ─── destroy ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_sequence(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $sequence = $this->createSequence($community);
        $campaignId = $sequence->campaign_id;

        $response = $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/email-sequences/{$sequence->id}");

        $response->assertRedirect(route('communities.email-sequences.index', $community));
        $this->assertDatabaseMissing('email_sequences', ['id' => $sequence->id]);
        $this->assertDatabaseMissing('email_campaigns', ['id' => $campaignId]);
    }

    // ─── authorization ──────────────────────────────────────────────────────────

    public function test_regular_member_cannot_access_sequences(): void
    {
        $owner   = User::factory()->create();
        $member  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}/email-sequences");

        $response->assertForbidden();
    }
}
