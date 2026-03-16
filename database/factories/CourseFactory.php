<?php

namespace Database\Factories;

use App\Models\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'title'        => fake()->sentence(3),
            'description'  => fake()->paragraph(),
            'cover_image'  => null,
            'position'     => 0,
        ];
    }
}
