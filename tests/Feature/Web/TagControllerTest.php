<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCommunity(): array
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        return [$owner, $community];
    }

    // ─── Index ──────────────────────────────────────────────────────────────────

    public function test_owner_can_list_tags(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        Tag::create(['community_id' => $community->id, 'name' => 'VIP', 'slug' => 'vip']);
        Tag::create(['community_id' => $community->id, 'name' => 'Active', 'slug' => 'active']);

        $this->actingAs($owner)
            ->getJson(route('communities.tags.index', $community))
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_non_owner_cannot_list_tags(): void
    {
        [, $community] = $this->ownerWithCommunity();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson(route('communities.tags.index', $community))
            ->assertForbidden();
    }

    public function test_guest_cannot_list_tags(): void
    {
        [, $community] = $this->ownerWithCommunity();

        $this->getJson(route('communities.tags.index', $community))
            ->assertUnauthorized();
    }

    // ─── Store ──────────────────────────────────────────────────────────────────

    public function test_owner_can_create_tag(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $this->actingAs($owner)
            ->post(route('communities.tags.store', $community), [
                'name'  => 'Premium',
                'color' => '#FF0000',
                'type'  => 'manual',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tags', [
            'community_id' => $community->id,
            'name'         => 'Premium',
            'slug'         => 'premium',
            'color'        => '#FF0000',
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $this->actingAs($owner)
            ->post(route('communities.tags.store', $community), [
                'name' => '',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_store_rejects_duplicate_slug(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        Tag::create(['community_id' => $community->id, 'name' => 'VIP', 'slug' => 'vip']);

        $this->actingAs($owner)
            ->post(route('communities.tags.store', $community), [
                'name' => 'VIP',
            ])
            ->assertSessionHasErrors('name');
    }

    // ─── Update ─────────────────────────────────────────────────────────────────

    public function test_owner_can_update_tag(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $tag = Tag::create(['community_id' => $community->id, 'name' => 'Old', 'slug' => 'old']);

        $this->actingAs($owner)
            ->patch(route('communities.tags.update', [$community, $tag]), [
                'name'  => 'New Name',
                'color' => '#00FF00',
            ])
            ->assertRedirect();

        $tag->refresh();
        $this->assertEquals('New Name', $tag->name);
        $this->assertEquals('new-name', $tag->slug);
    }

    public function test_update_rejects_duplicate_slug_from_another_tag(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        Tag::create(['community_id' => $community->id, 'name' => 'Existing', 'slug' => 'existing']);
        $tag = Tag::create(['community_id' => $community->id, 'name' => 'Other', 'slug' => 'other']);

        $this->actingAs($owner)
            ->patch(route('communities.tags.update', [$community, $tag]), [
                'name' => 'Existing',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_cannot_update_tag_from_another_community(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();
        $otherCommunity = Community::factory()->create();
        $tag = Tag::create(['community_id' => $otherCommunity->id, 'name' => 'Other', 'slug' => 'other']);

        $this->actingAs($owner)
            ->patch(route('communities.tags.update', [$community, $tag]), [
                'name' => 'Hacked',
            ])
            ->assertNotFound();
    }

    // ─── Destroy ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_tag(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $tag = Tag::create(['community_id' => $community->id, 'name' => 'Delete Me', 'slug' => 'delete-me']);

        $this->actingAs($owner)
            ->delete(route('communities.tags.destroy', [$community, $tag]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    // ─── Assign ─────────────────────────────────────────────────────────────────

    public function test_owner_can_assign_tags_to_members(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $tag    = Tag::create(['community_id' => $community->id, 'name' => 'VIP', 'slug' => 'vip']);
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);

        $this->actingAs($owner)
            ->post(route('communities.tags.assign', $community), [
                'member_ids' => [$member->id],
                'tag_ids'    => [$tag->id],
                'action'     => 'attach',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('community_member_tag', [
            'community_member_id' => $member->id,
            'tag_id'              => $tag->id,
        ]);
    }

    public function test_owner_can_detach_tags_from_members(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $tag    = Tag::create(['community_id' => $community->id, 'name' => 'VIP', 'slug' => 'vip']);
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);
        $member->tags()->attach($tag->id, ['tagged_at' => now()]);

        $this->actingAs($owner)
            ->post(route('communities.tags.assign', $community), [
                'member_ids' => [$member->id],
                'tag_ids'    => [$tag->id],
                'action'     => 'detach',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('community_member_tag', [
            'community_member_id' => $member->id,
            'tag_id'              => $tag->id,
        ]);
    }

    public function test_assign_validates_action(): void
    {
        [$owner, $community] = $this->ownerWithCommunity();

        $this->actingAs($owner)
            ->post(route('communities.tags.assign', $community), [
                'member_ids' => [1],
                'tag_ids'    => [1],
                'action'     => 'invalid',
            ])
            ->assertSessionHasErrors('action');
    }
}
