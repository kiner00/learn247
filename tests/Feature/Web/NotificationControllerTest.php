<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use App\Queries\Notification\GetNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── markAllRead ──────────────────────────────────────────────────────────

    public function test_mark_all_read_updates_unread_notifications(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['message' => 'Test notification 1'],
        ]);
        Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_comment',
            'data'         => ['message' => 'Test notification 2'],
        ]);

        $response = $this->actingAs($user)
            ->post('/notifications/read-all');

        $response->assertRedirect();

        $unread = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unread);
    }

    public function test_mark_all_read_does_not_affect_other_users(): void
    {
        $user      = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['message' => 'User notification'],
        ]);
        Notification::create([
            'user_id'      => $other->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['message' => 'Other notification'],
        ]);

        $this->actingAs($user)
            ->post('/notifications/read-all');

        $otherUnread = Notification::where('user_id', $other->id)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(1, $otherUnread);
    }

    public function test_guest_cannot_mark_all_read(): void
    {
        $response = $this->post('/notifications/read-all');

        $response->assertRedirect('/login');
    }

    // ─── recent ───────────────────────────────────────────────────────────────

    public function test_recent_returns_json_with_notifications(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['message' => 'Recent notification'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications/recent');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['type' => 'new_post']);
    }

    public function test_recent_returns_empty_when_no_notifications(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/notifications/recent');

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_recent_only_returns_own_notifications(): void
    {
        $user      = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        Notification::create([
            'user_id'      => $other->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['message' => 'Not for user'],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/notifications/recent');

        $response->assertOk()
            ->assertJsonCount(0);
    }

    public function test_guest_cannot_access_recent_notifications(): void
    {
        $response = $this->getJson('/notifications/recent');

        $response->assertUnauthorized();
    }

    public function test_mark_all_read_does_not_change_already_read_timestamps(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();
        $readAt    = now()->subHour();

        $notification = Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['message' => 'Already read'],
            'read_at'      => $readAt,
        ]);

        $this->actingAs($user)->post('/notifications/read-all');

        $notification->refresh();
        $this->assertEquals(
            $readAt->toDateTimeString(),
            $notification->read_at->toDateTimeString()
        );
    }

    public function test_recent_returns_correct_structure(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['name' => 'Test Community', 'slug' => 'test-community']);
        $actor     = User::factory()->create(['name' => 'Actor Name']);

        Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['post_id' => 99],
        ]);

        $response = $this->actingAs($user)->getJson('/notifications/recent');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'type'           => 'new_post',
                'actor_name'     => 'Actor Name',
                'community_name' => 'Test Community',
                'community_slug' => 'test-community',
            ]);
    }

    public function test_recent_returns_notifications_ordered_latest_first(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        $first = Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => ['order' => 1],
        ]);

        // Ensure second is created after first
        $first->created_at = now()->subMinute();
        $first->save();

        Notification::create([
            'user_id'      => $user->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_comment',
            'data'         => ['order' => 2],
        ]);

        $response = $this->actingAs($user)->getJson('/notifications/recent');

        $items = $response->json();
        $this->assertEquals('new_comment', $items[0]['type']);
        $this->assertEquals('new_post', $items[1]['type']);
    }

    public function test_mark_all_read_redirects_back(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/communities')
            ->post('/notifications/read-all');

        $response->assertRedirect('/communities');
    }

    // ─── GetNotifications::paginated ──────────────────────────────────────────

    public function test_paginated_returns_paginator_for_user(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            Notification::create([
                'user_id'      => $user->id,
                'actor_id'     => $actor->id,
                'community_id' => $community->id,
                'type'         => 'new_post',
                'data'         => ['index' => $i],
            ]);
        }

        $query  = app(GetNotifications::class);
        $result = $query->paginated($user, 2);

        $this->assertEquals(3, $result->total());
        $this->assertCount(2, $result->items());
    }

    public function test_paginated_only_returns_own_notifications(): void
    {
        $user      = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create();
        $actor     = User::factory()->create();

        Notification::create([
            'user_id'      => $other->id,
            'actor_id'     => $actor->id,
            'community_id' => $community->id,
            'type'         => 'new_post',
            'data'         => [],
        ]);

        $query  = app(GetNotifications::class);
        $result = $query->paginated($user);

        $this->assertEquals(0, $result->total());
    }
}
