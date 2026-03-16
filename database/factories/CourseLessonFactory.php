<?php

namespace Database\Factories;

use App\Models\CourseModule;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseLessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'module_id' => CourseModule::factory(),
            'title'     => fake()->sentence(3),
            'content'   => fake()->paragraphs(2, true),
            'video_url' => null,
            'position'  => 0,
        ];
    }
}
