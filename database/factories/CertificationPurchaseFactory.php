<?php

namespace Database\Factories;

use App\Models\CertificationPurchase;
use App\Models\CourseCertification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CertificationPurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'certification_id' => CourseCertification::factory(),
            'affiliate_id' => null,
            'xendit_id' => 'inv_'.fake()->unique()->uuid(),
            'status' => CertificationPurchase::STATUS_PENDING,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => CertificationPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}
