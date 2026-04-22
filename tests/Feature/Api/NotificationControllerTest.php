<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_gets_paginated_notifications(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'data' => ['message' => 'Test'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.type', 'test')
            ->assertJsonPath('data.0.data.message', 'Test');
    }

    public function test_post_read_all_marks_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'data' => ['message' => 'Test'],
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read.');

        $this->assertNotNull(Notification::where('user_id', $user->id)->first()->read_at);
    }

    public function test_unauthenticated_returns_401_for_get_notifications(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_read_all(): void
    {
        $this->postJson('/api/v1/notifications/read-all')
            ->assertUnauthorized();
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'data' => ['message' => 'Test'],
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('message', 'Notification marked as read.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_is_idempotent_and_preserves_timestamp(): void
    {
        $user = User::factory()->create();
        $readAt = now()->subHour();
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'test',
            'data' => ['message' => 'Test'],
            'read_at' => $readAt,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();

        $this->assertEquals(
            $readAt->toDateTimeString(),
            $notification->fresh()->read_at->toDateTimeString()
        );
    }

    public function test_user_cannot_mark_other_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::create([
            'user_id' => $other->id,
            'type' => 'test',
            'data' => ['message' => 'Test'],
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_unauthenticated_cannot_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'test',
            'data' => [],
            'read_at' => null,
        ]);

        $this->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertUnauthorized();
    }
}
