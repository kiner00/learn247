<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curzzo>
 */
class CurzzoFactory extends Factory
{
    protected $model = Curzzo::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'instructions' => fake()->paragraph(),
            'access_type' => 'free',
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function paidOnce(float $price = 99.00): static
    {
        return $this->state([
            'access_type' => 'paid_once',
            'price' => $price,
            'currency' => 'PHP',
            'billing_type' => 'one_time',
        ]);
    }

    public function paidMonthly(float $price = 49.00): static
    {
        return $this->state([
            'access_type' => 'paid_monthly',
            'price' => $price,
            'currency' => 'PHP',
            'billing_type' => 'monthly',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
