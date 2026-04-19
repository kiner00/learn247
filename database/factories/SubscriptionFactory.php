<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'status' => Subscription::STATUS_PENDING,
            'xendit_id' => 'inv_'.fake()->unique()->uuid(),
            'xendit_invoice_url' => 'https://checkout.xendit.co/'.fake()->uuid(),
            'expires_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => Subscription::STATUS_EXPIRED,
            'expires_at' => now()->subDay(),
        ]);
    }
}
