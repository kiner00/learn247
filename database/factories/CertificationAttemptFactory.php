<?php

namespace Database\Factories;

use App\Models\CourseCertification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationAttemptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'certification_id' => CourseCertification::factory(),
            'user_id' => User::factory(),
            'answers' => [],
            'score' => fake()->numberBetween(0, 100),
            'passed' => false,
            'completed_at' => now(),
        ];
    }

    public function passed(): static
    {
        return $this->state(['passed' => true, 'score' => 100]);
    }
}
