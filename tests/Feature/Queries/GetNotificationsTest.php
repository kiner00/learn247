<?php

namespace Tests\Feature\Queries;

use App\Models\Community;
use App\Models\Notification;
use App\Models\User;
use App\Queries\Notification\GetNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginated_returns_user_notifications(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $community = Community::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'community_id' => $community->id,
            'type' => 'like',
            'data' => ['post_id' => 1],
        ]);

        $query = new GetNotifications;
        $result = $query->paginated($user);

        $this->assertCount(1, $result->items());
        $this->assertEquals('like', $result->items()[0]->type);
    }

    public function test_paginated_does_not_return_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::create([
            'user_id' => $other->id,
            'type' => 'like',
            'data' => [],
        ]);

        $query = new GetNotifications;
        $result = $query->paginated($user);

        $this->assertCount(0, $result->items());
    }

    public function test_recent_returns_mapped_collection(): void
    {
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $community = Community::factory()->create();

        Notification::create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'community_id' => $community->id,
            'type' => 'comment',
            'data' => ['post_id' => 1],
        ]);

        $query = new GetNotifications;
        $result = $query->recent($user);

        $this->assertCount(1, $result);
        $first = $result->first();
        $this->assertEquals('comment', $first['type']);
        $this->assertEquals($actor->name, $first['actor_name']);
        $this->assertEquals($community->name, $first['community_name']);
        $this->assertEquals($community->slug, $first['community_slug']);
    }

    public function test_recent_respects_limit(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'like',
                'data' => [],
            ]);
        }

        $query = new GetNotifications;
        $result = $query->recent($user, 3);

        $this->assertCount(3, $result);
    }
}
