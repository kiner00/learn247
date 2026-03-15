<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\ManageEvent;
use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManageEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_event(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $action    = new ManageEvent();

        $event = $action->store($community, $user, [
            'title'       => 'Launch Party',
            'description' => 'Come celebrate!',
            'start_at'    => now()->addWeek()->toDateTimeString(),
            'timezone'    => 'Asia/Manila',
        ]);

        $this->assertDatabaseHas('events', [
            'id'           => $event->id,
            'community_id' => $community->id,
            'title'        => 'Launch Party',
        ]);
    }

    public function test_store_with_cover_image(): void
    {
        Storage::fake('public');
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $action    = new ManageEvent();
        $image     = UploadedFile::fake()->image('event-cover.jpg');

        $event = $action->store($community, $user, [
            'title'    => 'Image Event',
            'start_at' => now()->addWeek()->toDateTimeString(),
            'timezone' => 'UTC',
        ], $image);

        $this->assertNotNull($event->cover_image);
    }

    public function test_update_event_basic_fields(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $user->id,
            'title'        => 'Original',
            'description'  => 'Original desc',
            'start_at'     => now()->addWeek(),
            'timezone'     => 'UTC',
        ]);

        $action   = new ManageEvent();
        $updated = $action->update($event, [
            'title'       => 'Updated Title',
            'description' => 'Updated desc',
        ]);

        $this->assertEquals('Updated Title', $updated->title);
    }

    public function test_update_with_cover_image_replaces_old(): void
    {
        Storage::fake('public');
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $user->id,
            'title'        => 'Covered Event',
            'start_at'     => now()->addWeek(),
            'timezone'     => 'UTC',
            'cover_image'  => 'events/old.jpg',
        ]);

        $action    = new ManageEvent();
        $newImage  = UploadedFile::fake()->image('new-cover.jpg');
        $updated   = $action->update($event, ['title' => 'Still Covered'], $newImage);

        $this->assertNotEquals('events/old.jpg', $updated->cover_image);
    }

    public function test_destroy_deletes_event(): void
    {
        $community = Community::factory()->create();
        $user      = User::factory()->create();
        $event     = Event::create([
            'community_id' => $community->id,
            'created_by'   => $user->id,
            'title'        => 'Delete Me',
            'start_at'     => now()->addWeek(),
            'timezone'     => 'UTC',
        ]);

        $action = new ManageEvent();
        $action->destroy($event);

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
