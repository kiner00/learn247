<?php

namespace Tests\Feature\Listeners;

use App\Events\CourseEnrolled;
use App\Events\MemberJoined;
use App\Events\MemberTagged;
use App\Events\SubscriptionPaid;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExecuteWorkflowActionsTest extends TestCase
{
    use RefreshDatabase;

    private function setup_member(): array
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
        ]);
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'LEAD', 'slug' => 'lead']);

        return [$community, $member, $tag];
    }

    public function test_member_joined_event_applies_tag(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Tag on join',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        MemberJoined::dispatch($member);

        $this->assertTrue($member->fresh()->tags()->where('tag_id', $tag->id)->exists());
    }

    public function test_inactive_workflow_does_not_run(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Paused',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => false,
        ]);

        MemberJoined::dispatch($member);

        $this->assertFalse($member->fresh()->tags()->exists());
    }

    public function test_course_filter_is_respected(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Tag on course 42',
            'trigger_event' => Workflow::TRIGGER_COURSE_ENROLLED,
            'trigger_filter' => ['course_id' => 42],
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        CourseEnrolled::dispatch($member, 99);
        $this->assertFalse($member->fresh()->tags()->exists());

        CourseEnrolled::dispatch($member, 42);
        $this->assertTrue($member->fresh()->tags()->where('tag_id', $tag->id)->exists());
    }

    public function test_membership_type_filter_is_respected(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Free only',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'trigger_filter' => ['membership_type' => 'free'],
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        MemberJoined::dispatch($member); // paid member, should not match

        $this->assertFalse($member->fresh()->tags()->exists());
    }

    public function test_subscription_paid_triggers_workflow(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Tag on pay',
            'trigger_event' => Workflow::TRIGGER_SUBSCRIPTION_PAID,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        $sub = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->user_id,
        ]);
        SubscriptionPaid::dispatch($member, $sub);

        $this->assertTrue($member->fresh()->tags()->where('tag_id', $tag->id)->exists());
    }

    public function test_run_count_and_last_run_at_are_updated(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        $wf = Workflow::create([
            'community_id' => $community->id,
            'name' => 'Count',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        MemberJoined::dispatch($member);

        $wf->refresh();
        $this->assertSame(1, $wf->run_count);
        $this->assertNotNull($wf->last_run_at);
    }

    public function test_does_not_cross_communities(): void
    {
        [$community, $member, $tag] = $this->setup_member();
        $otherCommunity = Community::factory()->create();

        Workflow::create([
            'community_id' => $otherCommunity->id,
            'name' => 'Wrong comm',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        MemberJoined::dispatch($member);

        $this->assertFalse($member->fresh()->tags()->exists());
    }

    public function test_dispatches_member_tagged_only_on_first_apply(): void
    {
        [$community, $member, $tag] = $this->setup_member();

        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Wf',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        Event::fake([MemberTagged::class]);

        MemberJoined::dispatch($member);
        Event::assertDispatched(MemberTagged::class, 1);

        // Second run with same tag already applied should not re-dispatch
        MemberJoined::dispatch($member);
        Event::assertDispatched(MemberTagged::class, 1);
    }
}
