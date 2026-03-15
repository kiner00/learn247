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
            'type'    => 'test',
            'data'    => ['message' => 'Test'],
            'read_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/notifications');

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
            'type'    => 'test',
            'data'    => ['message' => 'Test'],
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read.');

        $this->assertNotNull(Notification::where('user_id', $user->id)->first()->read_at);
    }

    public function test_unauthenticated_returns_401_for_get_notifications(): void
    {
        $this->getJson('/api/notifications')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_read_all(): void
    {
        $this->postJson('/api/notifications/read-all')
            ->assertUnauthorized();
    }
}
