<?php

namespace Tests\Feature\Api;

use App\Actions\Community\ManageEvent;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
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

    // ─── delete event not belonging to community ─────────────────────────────

    public function test_delete_event_not_belonging_to_community_returns_404(): void
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
            ->deleteJson("/api/communities/{$communityA->slug}/events/{$event->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    // ─── error branch: store ─────────────────────────────────────────────────

    public function test_store_returns_500_when_action_throws(): void
    {
        $community = Community::factory()->create();

        $mock = Mockery::mock(ManageEvent::class);
        $mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('disk full'));
        $this->app->instance(ManageEvent::class, $mock);

        $this->actingAs($community->owner)
            ->postJson("/api/communities/{$community->slug}/events", [
                'title'    => 'Failing Event',
                'start_at' => now()->addDay()->toIso8601String(),
                'timezone' => 'UTC',
            ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to create event.');
    }

    // ─── error branch: update ────────────────────────────────────────────────

    public function test_update_returns_500_when_action_throws(): void
    {
        $community = Community::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Original',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $mock = Mockery::mock(ManageEvent::class);
        $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageEvent::class, $mock);

        $this->actingAs($community->owner)
            ->postJson("/api/communities/{$community->slug}/events/{$event->id}", [
                'title'    => 'Updated',
                'start_at' => now()->addDays(2)->toIso8601String(),
                'timezone' => 'UTC',
            ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to update event.');
    }

    // ─── error branch: destroy ───────────────────────────────────────────────

    public function test_destroy_returns_500_when_action_throws(): void
    {
        $community = Community::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'To Delete',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $mock = Mockery::mock(ManageEvent::class);
        $mock->shouldReceive('destroy')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageEvent::class, $mock);

        $this->actingAs($community->owner)
            ->deleteJson("/api/communities/{$community->slug}/events/{$event->id}")
            ->assertStatus(500)
            ->assertJsonPath('message', 'Failed to delete event.');
    }

    // ─── non-owner cannot update event ───────────────────────────────────────

    public function test_non_owner_cannot_update_event(): void
    {
        $community = Community::factory()->create();
        $nonOwner  = User::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Protected',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $this->actingAs($nonOwner)
            ->postJson("/api/communities/{$community->slug}/events/{$event->id}", [
                'title'    => 'Hacked',
                'start_at' => now()->addDays(2)->toIso8601String(),
                'timezone' => 'UTC',
            ])
            ->assertForbidden();
    }

    // ─── non-owner cannot delete event ───────────────────────────────────────

    public function test_non_owner_cannot_delete_event(): void
    {
        $community = Community::factory()->create();
        $nonOwner  = User::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Protected',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
        ]);

        $this->actingAs($nonOwner)
            ->deleteJson("/api/communities/{$community->slug}/events/{$event->id}")
            ->assertForbidden();
    }

    // ─── index: event with cover_image and null end_at ───────────────────────

    public function test_index_returns_events_with_cover_image_and_null_end_at(): void
    {
        Storage::fake(config('filesystems.default'));

        $community = Community::factory()->create();
        $owner     = $community->owner;

        Event::create([
            'community_id' => $community->id,
            'created_by'   => $owner->id,
            'title'        => 'Open-ended Event',
            'start_at'     => now()->addDay(),
            'end_at'       => null,
            'timezone'     => 'UTC',
            'cover_image'  => 'events/1/cover.jpg',
            'visibility'   => 'public',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/communities/{$community->slug}/events")
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.title', 'Open-ended Event')
            ->assertJsonPath('events.0.end_at', null);
    }

    // ─── index: with explicit year/month params ──────────────────────────────

    public function test_index_filters_by_year_and_month_params(): void
    {
        $community = Community::factory()->create();
        $owner     = $community->owner;

        // Create an event in January 2025
        Event::create([
            'community_id' => $community->id,
            'created_by'   => $owner->id,
            'title'        => 'Jan Event',
            'start_at'     => '2025-01-15 10:00:00',
            'end_at'       => '2025-01-15 12:00:00',
            'timezone'     => 'UTC',
            'visibility'   => 'public',
        ]);

        // Create an event in February 2025
        Event::create([
            'community_id' => $community->id,
            'created_by'   => $owner->id,
            'title'        => 'Feb Event',
            'start_at'     => '2025-02-15 10:00:00',
            'end_at'       => '2025-02-15 12:00:00',
            'timezone'     => 'UTC',
            'visibility'   => 'public',
        ]);

        $this->actingAs($owner)
            ->getJson("/api/communities/{$community->slug}/events?year=2025&month=1")
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.title', 'Jan Event')
            ->assertJsonPath('year', 2025)
            ->assertJsonPath('month', 1);
    }

    // ─── index: unauthenticated sees only public ─────────────────────────────

    public function test_unauthenticated_index_sees_only_public_events(): void
    {
        $community = Community::factory()->create();

        Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Public Event',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
            'visibility'   => 'public',
        ]);

        Event::create([
            'community_id' => $community->id,
            'created_by'   => $community->owner_id,
            'title'        => 'Free Event',
            'start_at'     => now()->addDay(),
            'end_at'       => now()->addDay()->addHour(),
            'visibility'   => 'free',
        ]);

        $this->getJson("/api/communities/{$community->slug}/events")
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.title', 'Public Event');
    }
}
