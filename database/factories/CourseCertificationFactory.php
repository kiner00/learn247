<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseCertificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'title' => fake()->sentence(3),
            'cert_title' => fake()->sentence(2),
            'description' => fake()->paragraph(),
            'cover_image' => null,
            'pass_score' => 70,
            'randomize_questions' => false,
            'price' => 0,
            'affiliate_commission_rate' => null,
        ];
    }

    public function paid(float $price = 499.00): static
    {
        return $this->state(['price' => $price]);
    }
}
