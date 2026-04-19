<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\Course;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCommunity(): array
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $community];
    }

    // ─── Index (settings page) ──────────────────────────────────────────────────

    public function test_owner_can_view_workflows_settings_with_data(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $tag = Tag::create(['community_id' => $community->id, 'name' => 'LEAD', 'slug' => 'lead']);
        Workflow::create([
            'community_id' => $community->id,
            'name' => 'Tag new members',
            'trigger_event' => Workflow::TRIGGER_MEMBER_JOINED,
            'action_type' => Workflow::ACTION_APPLY_TAG,
            'action_config' => ['tag_id' => $tag->id],
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings/workflows")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Communities/Settings/Workflows')
                ->has('workflows', 1)
                ->has('tags', 1)
            );
    }

    // ─── Store ──────────────────────────────────────────────────────────────────

    public function test_owner_can_create_workflow(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'LEAD', 'slug' => 'lead']);

        $this->actingAs($owner)
            ->post(route('communities.workflows.store', $community), [
                'name' => 'Tag paid members',
                'trigger_event' => 'member_joined',
                'action_type' => 'apply_tag',
                'tag_id' => $tag->id,
                'membership_type' => 'paid',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('workflows', [
            'community_id' => $community->id,
            'name' => 'Tag paid members',
            'trigger_event' => 'member_joined',
            'is_active' => true,
        ]);

        $wf = Workflow::where('community_id', $community->id)->first();
        $this->assertSame(['membership_type' => 'paid'], $wf->trigger_filter);
        $this->assertSame(['tag_id' => $tag->id], $wf->action_config);
    }

    public function test_store_builds_course_filter(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'Enrolled', 'slug' => 'enrolled']);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $this->actingAs($owner)
            ->post(route('communities.workflows.store', $community), [
                'name' => 'Tag on course',
                'trigger_event' => 'course_enrolled',
                'action_type' => 'apply_tag',
                'tag_id' => $tag->id,
                'course_id' => $course->id,
            ])
            ->assertRedirect();

        $wf = Workflow::where('community_id', $community->id)->first();
        $this->assertSame(['course_id' => $course->id], $wf->trigger_filter);
    }

    public function test_store_rejects_invalid_trigger(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'LEAD', 'slug' => 'lead']);

        $this->actingAs($owner)
            ->post(route('communities.workflows.store', $community), [
                'name' => 'Bad',
                'trigger_event' => 'bogus',
                'action_type' => 'apply_tag',
                'tag_id' => $tag->id,
            ])
            ->assertSessionHasErrors('trigger_event');
    }

    public function test_store_rejects_tag_from_another_community(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $other = Community::factory()->create();
        $foreignTag = Tag::create(['community_id' => $other->id, 'name' => 'Foreign', 'slug' => 'foreign']);

        $this->actingAs($owner)
            ->post(route('communities.workflows.store', $community), [
                'name' => 'X',
                'trigger_event' => 'member_joined',
                'action_type' => 'apply_tag',
                'tag_id' => $foreignTag->id,
            ])
            ->assertSessionHasErrors('tag_id');
    }

    public function test_non_owner_cannot_create_workflow(): void
    {
        [, $community] = $this->ownerWithCommunity();
        $stranger = User::factory()->create();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'T', 'slug' => 't']);

        $this->actingAs($stranger)
            ->post(route('communities.workflows.store', $community), [
                'name' => 'Nope',
                'trigger_event' => 'member_joined',
                'action_type' => 'apply_tag',
                'tag_id' => $tag->id,
            ])
            ->assertForbidden();
    }

    // ─── Update ─────────────────────────────────────────────────────────────────

    public function test_owner_can_update_workflow(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $tag1 = Tag::create(['community_id' => $community->id, 'name' => 'A', 'slug' => 'a']);
        $tag2 = Tag::create(['community_id' => $community->id, 'name' => 'B', 'slug' => 'b']);

        $wf = Workflow::create([
            'community_id' => $community->id,
            'name' => 'Original',
            'trigger_event' => 'member_joined',
            'action_type' => 'apply_tag',
            'action_config' => ['tag_id' => $tag1->id],
        ]);

        $this->actingAs($owner)
            ->patch(route('communities.workflows.update', [$community, $wf]), [
                'name' => 'Renamed',
                'trigger_event' => 'subscription_paid',
                'action_type' => 'apply_tag',
                'tag_id' => $tag2->id,
            ])
            ->assertRedirect();

        $wf->refresh();
        $this->assertSame('Renamed', $wf->name);
        $this->assertSame('subscription_paid', $wf->trigger_event);
        $this->assertSame(['tag_id' => $tag2->id], $wf->action_config);
    }

    public function test_cannot_update_workflow_from_another_community(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $other = Community::factory()->create();
        $tag = Tag::create(['community_id' => $other->id, 'name' => 'X', 'slug' => 'x']);

        $wf = Workflow::create([
            'community_id' => $other->id,
            'name' => 'Other',
            'trigger_event' => 'member_joined',
            'action_type' => 'apply_tag',
            'action_config' => ['tag_id' => $tag->id],
        ]);

        $myTag = Tag::create(['community_id' => $community->id, 'name' => 'M', 'slug' => 'm']);

        $this->actingAs($owner)
            ->patch(route('communities.workflows.update', [$community, $wf]), [
                'name' => 'Hacked',
                'trigger_event' => 'member_joined',
                'action_type' => 'apply_tag',
                'tag_id' => $myTag->id,
            ])
            ->assertNotFound();
    }

    // ─── Toggle ─────────────────────────────────────────────────────────────────

    public function test_toggle_flips_active_flag(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'T', 'slug' => 't']);

        $wf = Workflow::create([
            'community_id' => $community->id,
            'name' => 'Wf',
            'trigger_event' => 'member_joined',
            'action_type' => 'apply_tag',
            'action_config' => ['tag_id' => $tag->id],
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('communities.workflows.toggle', [$community, $wf]))
            ->assertRedirect();

        $this->assertFalse($wf->fresh()->is_active);
    }

    // ─── Destroy ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_workflow(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'T', 'slug' => 't']);

        $wf = Workflow::create([
            'community_id' => $community->id,
            'name' => 'Wf',
            'trigger_event' => 'member_joined',
            'action_type' => 'apply_tag',
            'action_config' => ['tag_id' => $tag->id],
        ]);

        $this->actingAs($owner)
            ->delete(route('communities.workflows.destroy', [$community, $wf]))
            ->assertRedirect();

        $this->assertDatabaseMissing('workflows', ['id' => $wf->id]);
    }

    public function test_non_owner_cannot_delete_workflow(): void
    {
        [, $community] = $this->ownerWithCommunity();
        $stranger = User::factory()->create();
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'T', 'slug' => 't']);

        $wf = Workflow::create([
            'community_id' => $community->id,
            'name' => 'Wf',
            'trigger_event' => 'member_joined',
            'action_type' => 'apply_tag',
            'action_config' => ['tag_id' => $tag->id],
        ]);

        $this->actingAs($stranger)
            ->delete(route('communities.workflows.destroy', [$community, $wf]))
            ->assertForbidden();
    }
}
