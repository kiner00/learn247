<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(),
            'content' => fake()->paragraphs(2, true),
            'is_pinned' => false,
        ];
    }
}
