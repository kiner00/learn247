<?php

namespace Tests\Feature\Web;

use App\Actions\Community\ManageEvent;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class EventControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_can_be_accessed_unauthenticated(): void
    {
        $community = Community::factory()->create();

        $response = $this->get("/communities/{$community->slug}/calendar");

        $response->assertOk();
    }

    public function test_index_returns_inertia_calendar_view(): void
    {
        $community = Community::factory()->create();

        $response = $this->get("/communities/{$community->slug}/calendar");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Calendar')
            ->has('community')
            ->has('events')
            ->has('year')
            ->has('month')
        );
    }

    public function test_index_shows_only_public_events_for_unauthenticated_user(): void
    {
        $community = Community::factory()->create();
        $year = now()->year;
        $month = now()->month;
        $startAt = now()->setDate($year, $month, 15)->startOfDay();

        Event::create([
            'community_id' => $community->id,
            'created_by' => $community->owner_id,
            'title' => 'Public Event',
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(1),
            'timezone' => 'UTC',
            'visibility' => 'public',
        ]);
        Event::create([
            'community_id' => $community->id,
            'created_by' => $community->owner_id,
            'title' => 'Members Only Event',
            'start_at' => $startAt->copy()->addHours(2),
            'end_at' => $startAt->copy()->addHours(3),
            'timezone' => 'UTC',
            'visibility' => 'free',
        ]);

        $response = $this->get("/communities/{$community->slug}/calendar");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Public Event')
        );
    }

    public function test_index_shows_all_events_for_authenticated_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        $year = now()->year;
        $month = now()->month;
        $startAt = now()->setDate($year, $month, 15)->startOfDay();

        Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Public Event',
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(1),
            'timezone' => 'UTC',
            'visibility' => 'public',
        ]);
        Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Members Only Event',
            'start_at' => $startAt->copy()->addHours(2),
            'end_at' => $startAt->copy()->addHours(3),
            'timezone' => 'UTC',
            'visibility' => 'free',
        ]);

        $response = $this->actingAs($member)->get("/communities/{$community->slug}/calendar");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('events', 2));
    }

    public function test_index_shows_all_events_for_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $year = now()->year;
        $month = now()->month;
        $startAt = now()->setDate($year, $month, 15)->startOfDay();

        Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Members Only Event',
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(1),
            'timezone' => 'UTC',
            'visibility' => 'free',
        ]);

        $response = $this->actingAs($owner)->get("/communities/{$community->slug}/calendar");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Members Only Event')
        );
    }

    public function test_index_filters_by_year_and_month(): void
    {
        $community = Community::factory()->create();
        $year = now()->year;
        $month = now()->month;
        $startAt = now()->setDate($year, $month, 10)->startOfDay();

        Event::create([
            'community_id' => $community->id,
            'created_by' => $community->owner_id,
            'title' => 'Event This Month',
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(1),
            'timezone' => 'UTC',
            'visibility' => 'public',
        ]);

        $response = $this->get("/communities/{$community->slug}/calendar?year={$year}&month={$month}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.title', 'Event This Month')
            ->where('year', $year)
            ->where('month', $month)
        );
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_owner_can_create_event_without_cover_image(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events", [
            'title' => 'New Event',
            'description' => 'Event description',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'end_at' => now()->addDays(1)->addHours(2)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Event created.');
        $this->assertDatabaseHas('events', [
            'community_id' => $community->id,
            'title' => 'New Event',
            'description' => 'Event description',
        ]);
    }

    public function test_owner_can_create_event_with_cover_image(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $file = UploadedFile::fake()->image('event.jpg');

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events", [
            'title' => 'Event With Cover',
            'description' => null,
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
            'cover_image' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Event created.');
        $this->assertDatabaseHas('events', ['title' => 'Event With Cover']);
        $event = Event::where('title', 'Event With Cover')->first();
        $this->assertNotNull($event->cover_image);
    }

    public function test_non_owner_cannot_create_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($other)->post("/communities/{$community->slug}/events", [
            'title' => 'Hacked Event',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('events', ['title' => 'Hacked Event']);
    }

    public function test_unauthenticated_user_cannot_create_event(): void
    {
        $community = Community::factory()->create();

        $response = $this->post("/communities/{$community->slug}/events", [
            'title' => 'Guest Event',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_store_requires_title(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events", [
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertSessionHasErrors(['title']);
    }

    // ─── update ──────────────────────────────────────────────────────────────

    public function test_owner_can_update_event(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Original Title',
            'start_at' => now()->addDays(1),
            'end_at' => now()->addDays(1)->addHours(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events/{$event->id}", [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'start_at' => now()->addDays(2)->toDateTimeString(),
            'end_at' => now()->addDays(2)->addHours(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Event updated.');
        $event->refresh();
        $this->assertSame('Updated Title', $event->title);
        $this->assertSame('Updated description', $event->description);
    }

    public function test_owner_can_update_event_with_cover_image_replacement(): void
    {
        $disk = config('filesystems.default');
        Storage::fake($disk);

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Event With Cover',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
            'cover_image' => 'events/1/old.jpg',
        ]);
        Storage::disk($disk)->put('events/1/old.jpg', 'fake');

        $newFile = UploadedFile::fake()->image('new-cover.jpg');

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events/{$event->id}", [
            'title' => 'Event With Cover',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
            'cover_image' => $newFile,
        ]);

        $response->assertRedirect();
        $event->refresh();
        $this->assertNotNull($event->cover_image);
        $this->assertStringContainsString('events/', $event->cover_image);
    }

    public function test_non_owner_cannot_update_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Original',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($other)->post("/communities/{$community->slug}/events/{$event->id}", [
            'title' => 'Hacked Title',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertForbidden();
        $event->refresh();
        $this->assertSame('Original', $event->title);
    }

    public function test_update_returns_404_when_event_belongs_to_different_community(): void
    {
        $owner = User::factory()->create();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $eventInB = Event::create([
            'community_id' => $communityB->id,
            'created_by' => $owner->id,
            'title' => 'Event in B',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($owner)->post("/communities/{$communityA->slug}/events/{$eventInB->id}", [
            'title' => 'Should Fail',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertNotFound();
        $eventInB->refresh();
        $this->assertSame('Event in B', $eventInB->title);
    }

    // ─── destroy ─────────────────────────────────────────────────────────────

    public function test_owner_can_delete_event(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'To Delete',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($owner)->delete("/communities/{$community->slug}/events/{$event->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Event deleted.');
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_non_owner_cannot_delete_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Protected',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($other)->delete("/communities/{$community->slug}/events/{$event->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    public function test_destroy_returns_404_when_event_belongs_to_different_community(): void
    {
        $owner = User::factory()->create();
        $communityA = Community::factory()->create(['owner_id' => $owner->id]);
        $communityB = Community::factory()->create(['owner_id' => $owner->id]);
        $eventInB = Event::create([
            'community_id' => $communityB->id,
            'created_by' => $owner->id,
            'title' => 'Event in B',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->actingAs($owner)->delete("/communities/{$communityA->slug}/events/{$eventInB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('events', ['id' => $eventInB->id]);
    }

    // ─── error branch: store ─────────────────────────────────────────────────

    public function test_store_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mock = Mockery::mock(ManageEvent::class);
        $mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('disk full'));
        $this->app->instance(ManageEvent::class, $mock);

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events", [
            'title' => 'Failing Event',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to create event.');
    }

    // ─── error branch: update ────────────────────────────────────────────────

    public function test_update_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'Original',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $mock = Mockery::mock(ManageEvent::class);
        $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageEvent::class, $mock);

        $response = $this->actingAs($owner)->post("/communities/{$community->slug}/events/{$event->id}", [
            'title' => 'Updated',
            'start_at' => now()->addDays(2)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to update event.');
    }

    // ─── error branch: destroy ───────────────────────────────────────────────

    public function test_destroy_returns_error_session_when_action_throws(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $owner->id,
            'title' => 'To Delete',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $mock = Mockery::mock(ManageEvent::class);
        $mock->shouldReceive('destroy')->once()->andThrow(new \RuntimeException('db error'));
        $this->app->instance(ManageEvent::class, $mock);

        $response = $this->actingAs($owner)->delete("/communities/{$community->slug}/events/{$event->id}");

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Failed to delete event.');
    }

    // ─── guest cannot destroy ────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_delete_event(): void
    {
        $community = Community::factory()->create();
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $community->owner_id,
            'title' => 'Protected',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->delete("/communities/{$community->slug}/events/{$event->id}");

        $response->assertRedirect('/login');
    }

    // ─── guest cannot update ─────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_update_event(): void
    {
        $community = Community::factory()->create();
        $event = Event::create([
            'community_id' => $community->id,
            'created_by' => $community->owner_id,
            'title' => 'Protected',
            'start_at' => now()->addDays(1),
            'timezone' => 'UTC',
        ]);

        $response = $this->post("/communities/{$community->slug}/events/{$event->id}", [
            'title' => 'Hacked',
            'start_at' => now()->addDays(1)->toDateTimeString(),
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect('/login');
    }

    // ─── index with userTimezone ─────────────────────────────────────────────

    public function test_index_returns_user_timezone_for_authenticated_user(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $community = Community::factory()->create();

        $response = $this->actingAs($user)->get("/communities/{$community->slug}/calendar");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('userTimezone', 'Asia/Jakarta')
        );
    }
}
