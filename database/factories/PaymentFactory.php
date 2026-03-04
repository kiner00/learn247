<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $subscription = Subscription::factory()->create();

        return [
            'subscription_id'    => $subscription->id,
            'community_id'       => $subscription->community_id,
            'user_id'            => $subscription->user_id,
            'amount'             => fake()->randomFloat(2, 100, 2000),
            'currency'           => 'PHP',
            'status'             => Payment::STATUS_PAID,
            'provider_reference' => 'pay_' . fake()->uuid(),
            'xendit_event_id'    => 'inv_' . fake()->unique()->uuid() . '_PAID',
            'metadata'           => [],
            'paid_at'            => now(),
        ];
    }
}
