<?php

namespace Tests\Feature\Actions\Notification;

use App\Actions\Notification\MarkAllAsRead;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use RefreshDatabase;

    private MarkAllAsRead $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new MarkAllAsRead;
    }

    public function test_marks_all_unread_notifications_as_read(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'type' => 'test', 'data' => []]);
        Notification::create(['user_id' => $user->id, 'type' => 'test', 'data' => []]);

        $count = $this->action->execute($user);

        $this->assertSame(2, $count);
        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_does_not_affect_already_read_notifications(): void
    {
        $user = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'type' => 'test', 'data' => [], 'read_at' => now()]);
        Notification::create(['user_id' => $user->id, 'type' => 'test', 'data' => []]);

        $count = $this->action->execute($user);

        $this->assertSame(1, $count);
    }

    public function test_does_not_affect_other_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::create(['user_id' => $user->id, 'type' => 'test', 'data' => []]);
        Notification::create(['user_id' => $other->id, 'type' => 'test', 'data' => []]);

        $this->action->execute($user);

        $this->assertSame(1, Notification::where('user_id', $other->id)->whereNull('read_at')->count());
    }

    public function test_returns_zero_when_no_unread_notifications(): void
    {
        $user = User::factory()->create();

        $count = $this->action->execute($user);

        $this->assertSame(0, $count);
    }
}
