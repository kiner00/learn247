<?php

namespace App\Billing;

use App\Models\User;

class CheckoutContext
{
    public function __construct(
        public readonly User $user,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $referenceId,
        public readonly string $successUrl,
        public readonly string $failureUrl,
        public readonly string $itemName,
        public readonly string $itemCategory,
    ) {}
}
