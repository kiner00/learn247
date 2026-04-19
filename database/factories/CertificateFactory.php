<?php

namespace Database\Factories;

use App\Models\CourseCertification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'certification_id' => CourseCertification::factory(),
            'issued_at' => now(),
            'cert_title' => fake()->sentence(2),
            'description' => fake()->paragraph(),
            'cover_image' => null,
        ];
    }
}
