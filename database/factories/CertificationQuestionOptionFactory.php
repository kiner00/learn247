<?php

namespace Database\Factories;

use App\Models\CertificationQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationQuestionOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question_id' => CertificationQuestion::factory(),
            'label' => fake()->word(),
            'is_correct' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(['is_correct' => true]);
    }
}
