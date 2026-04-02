<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CommunityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name'        => ucwords($name),
            'slug'        => Str::slug($name),
            'owner_id'    => User::factory()->kycVerified(),
            'description' => fake()->paragraph(),
            'avatar'      => null,
            'is_private'  => false,
            'price'       => 0,
            'currency'    => 'PHP',
        ];
    }

    public function paid(float $price = 499.00): static
    {
        return $this->state(['price' => $price, 'is_private' => true]);
    }

    public function private(): static
    {
        return $this->state(['is_private' => true]);
    }
}
