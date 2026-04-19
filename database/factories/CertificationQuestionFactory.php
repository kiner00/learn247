<?php

namespace Database\Factories;

use App\Models\CourseCertification;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'certification_id' => CourseCertification::factory(),
            'question' => fake()->sentence().'?',
            'type' => 'multiple_choice',
            'position' => 0,
        ];
    }

    public function trueFalse(): static
    {
        return $this->state(['type' => 'true_false']);
    }
}
