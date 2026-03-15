<?php

namespace Tests\Feature\Api;

use App\Models\Community;
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
}
