<?php

namespace Tests\Unit\Models;

use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_read_returns_false_when_read_at_is_null(): void
    {
        $notification = new Notification;
        $notification->read_at = null;

        $this->assertFalse($notification->isRead());
    }

    public function test_is_read_returns_true_when_read_at_is_set(): void
    {
        $notification = new Notification;
        $notification->read_at = Carbon::now();

        $this->assertTrue($notification->isRead());
    }

    public function test_status_constants_are_defined(): void
    {
        $notification = new Notification;

        $this->assertContains('user_id', $notification->getFillable());
        $this->assertContains('actor_id', $notification->getFillable());
        $this->assertContains('community_id', $notification->getFillable());
        $this->assertContains('type', $notification->getFillable());
        $this->assertContains('data', $notification->getFillable());
        $this->assertContains('read_at', $notification->getFillable());
    }

    public function test_data_is_cast_to_array(): void
    {
        $casts = (new Notification)->getCasts();

        $this->assertArrayHasKey('data', $casts);
        $this->assertEquals('array', $casts['data']);
    }

    public function test_read_at_is_cast_to_datetime(): void
    {
        $casts = (new Notification)->getCasts();

        $this->assertArrayHasKey('read_at', $casts);
        $this->assertEquals('datetime', $casts['read_at']);
    }

    // ─── relationships ────────────────────────────────────────────────────────────

    public function test_user_relationship_returns_correct_user(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $community = Community::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'community_id' => $community->id,
            'type' => 'new_post',
            'data' => [],
        ]);

        $this->assertEquals($user->id, $notification->user->id);
    }

    public function test_actor_relationship_returns_correct_user(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $community = Community::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'community_id' => $community->id,
            'type' => 'new_post',
            'data' => [],
        ]);

        $this->assertEquals($actor->id, $notification->actor->id);
    }

    public function test_community_relationship_returns_correct_community(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $community = Community::factory()->create();

        $notification = Notification::create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'community_id' => $community->id,
            'type' => 'new_post',
            'data' => [],
        ]);

        $this->assertEquals($community->id, $notification->community->id);
    }
}
