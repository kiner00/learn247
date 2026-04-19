<?php

namespace Tests\Feature\Console;

use App\Events\CartAbandoned;
use App\Models\CartEvent;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class DetectAbandonedCartsTest extends TestCase
{
    use RefreshDatabase;

    private function insertCartEvent(int $communityId, int $userId, string $email, string $eventType, string $createdAt): int
    {
        return DB::table('cart_events')->insertGetId([
            'community_id' => $communityId,
            'user_id' => $userId,
            'email' => $email,
            'event_type' => $eventType,
            'abandoned_email_sent' => false,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_detects_abandoned_carts_and_fires_events(): void
    {
        Event::fake([CartAbandoned::class]);

        $community = Community::factory()->create();
        $user = User::factory()->create();

        $this->insertCartEvent(
            $community->id, $user->id, $user->email,
            CartEvent::TYPE_CHECKOUT_STARTED,
            now()->subHours(2)->toDateTimeString()
        );

        $this->artisan('carts:detect-abandoned --hours=1')
            ->assertSuccessful()
            ->expectsOutputToContain('Detected 1 abandoned cart');

        Event::assertDispatched(CartAbandoned::class, 1);

        $this->assertDatabaseHas('cart_events', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'event_type' => CartEvent::TYPE_ABANDONED,
            'abandoned_email_sent' => true,
        ]);
    }

    public function test_ignores_carts_with_completed_payment(): void
    {
        Event::fake([CartAbandoned::class]);

        $community = Community::factory()->create();
        $user = User::factory()->create();

        $this->insertCartEvent(
            $community->id, $user->id, $user->email,
            CartEvent::TYPE_CHECKOUT_STARTED,
            now()->subHours(2)->toDateTimeString()
        );
        $this->insertCartEvent(
            $community->id, $user->id, $user->email,
            CartEvent::TYPE_PAYMENT_COMPLETED,
            now()->subHour()->toDateTimeString()
        );

        $this->artisan('carts:detect-abandoned --hours=1')
            ->assertSuccessful()
            ->expectsOutputToContain('No abandoned carts found');

        Event::assertNotDispatched(CartAbandoned::class);
    }

    public function test_ignores_recent_carts_within_threshold(): void
    {
        Event::fake([CartAbandoned::class]);

        $community = Community::factory()->create();
        $user = User::factory()->create();

        $this->insertCartEvent(
            $community->id, $user->id, $user->email,
            CartEvent::TYPE_CHECKOUT_STARTED,
            now()->subMinutes(30)->toDateTimeString()
        );

        $this->artisan('carts:detect-abandoned --hours=1')
            ->assertSuccessful()
            ->expectsOutputToContain('No abandoned carts found');

        Event::assertNotDispatched(CartAbandoned::class);
    }
}
