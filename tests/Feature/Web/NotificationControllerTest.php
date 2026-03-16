<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
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
}
