<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_events_lists_events(): void
    {
        $community = Community::factory()->create();
        Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Test Event',
            'description'  => 'Desc',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $response = $this->actingAs($community->owner)->getJson("/api/communities/{$community->slug}/events");

        $response->assertOk()
            ->assertJsonStructure(['events', 'year', 'month'])
            ->assertJsonPath('events.0.title', 'Test Event');
    }

    public function test_owner_can_create_event_returns_201(): void
    {
        $community = Community::factory()->create();
        $owner     = $community->owner;

        $response = $this->actingAs($owner)->postJson("/api/communities/{$community->slug}/events", [
            'title'       => 'New Event',
            'description' => 'Event description',
            'start_at'    => now()->addDay()->toIso8601String(),
            'end_at'      => now()->addDay()->addHour()->toIso8601String(),
            'timezone'    => 'UTC',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Event created.')
            ->assertJsonStructure(['event_id']);
        $this->assertDatabaseHas('events', [
            'community_id' => $community->id,
            'title'        => 'New Event',
        ]);
    }

    public function test_non_owner_cannot_create_event_returns_403(): void
    {
        $community = Community::factory()->create();
        $nonOwner  = User::factory()->create();

        $response = $this->actingAs($nonOwner)->postJson("/api/communities/{$community->slug}/events", [
            'title'       => 'New Event',
            'description' => 'Event description',
            'start_at'    => now()->addDay()->toIso8601String(),
            'end_at'      => now()->addDay()->addHour()->toIso8601String(),
            'timezone'    => 'UTC',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('events', ['title' => 'New Event']);
    }

    public function test_owner_can_update_event(): void
    {
        $community = Community::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Test Event',
            'description'  => 'Desc',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $response = $this->actingAs($community->owner)->postJson(
            "/api/communities/{$community->slug}/events/{$event->id}",
            [
                'title'       => 'Updated Event',
                'description' => 'Updated desc',
                'start_at'    => now()->addDays(2)->toIso8601String(),
                'end_at'      => now()->addDays(2)->addHour()->toIso8601String(),
                'timezone'    => 'UTC',
            ]
        );

        $response->assertOk()->assertJsonPath('message', 'Event updated.');
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Updated Event']);
    }

    public function test_owner_can_delete_event(): void
    {
        $community = Community::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Test Event',
            'description'  => 'Desc',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $response = $this->actingAs($community->owner)->deleteJson(
            "/api/communities/{$community->slug}/events/{$event->id}"
        );

        $response->assertOk()->assertJsonPath('message', 'Event deleted.');
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $community = Community::factory()->create();

        $this->postJson("/api/communities/{$community->slug}/events", [
            'title'    => 'Test',
            'start_at' => now()->addDay()->toIso8601String(),
            'timezone' => 'UTC',
        ])->assertUnauthorized();
    }

    public function test_non_member_only_sees_public_events(): void
    {
        $community = Community::factory()->create();
        $outsider  = User::factory()->create();

        Event::create([
            'community_id'    => $community->id,
            'created_by'      => $community->owner_id,
            'title'           => 'Public Event',
            'start_at'        => now()->addDay(),
            'end_at'          => now()->addDay()->addHour(),
            'visibility'      => 'public',
        ]);

        Event::create([
            'community_id'    => $community->id,
            'created_by'      => $community->owner_id,
            'title'           => 'Members Only Event',
            'start_at'        => now()->addDay(),
            'end_at'          => now()->addDay()->addHour(),
            'visibility'      => 'free',
        ]);

        $this->actingAs($outsider)
            ->getJson("/api/communities/{$community->slug}/events")
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.title', 'Public Event');
    }

    public function test_member_sees_all_events_including_members_only(): void
    {
        $community = Community::factory()->create();
        $member    = User::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Event::create([
            'community_id'    => $community->id,
            'created_by'      => $community->owner_id,
            'title'           => 'Public Event',
            'start_at'        => now()->addDay(),
            'end_at'          => now()->addDay()->addHour(),
            'visibility'      => 'public',
        ]);

        Event::create([
            'community_id'    => $community->id,
            'created_by'      => $community->owner_id,
            'title'           => 'Members Only Event',
            'start_at'        => now()->addDay(),
            'end_at'          => now()->addDay()->addHour(),
            'visibility'      => 'free',
        ]);

        $this->actingAs($member)
            ->getJson("/api/communities/{$community->slug}/events")
            ->assertOk()
            ->assertJsonCount(2, 'events');
    }

    public function test_update_event_not_belonging_to_community_returns_404(): void
    {
        $communityA = Community::factory()->create();
        $communityB = Community::factory()->create(['owner_id' => $communityA->owner_id]);

        $event = Event::create([
            'community_id' => $communityB->id,
            'created_by'   => $communityA->owner_id,
            'title'        => 'Wrong Community Event',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $this->actingAs($communityA->owner)
            ->postJson("/api/communities/{$communityA->slug}/events/{$event->id}", [
                'title'    => 'Hacked',
                'start_at' => now()->addDays(2)->toIso8601String(),
                'timezone' => 'UTC',
            ])
            ->assertNotFound();
    }
}
