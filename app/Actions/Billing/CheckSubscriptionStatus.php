<?php

namespace App\Actions\Billing;

use App\Models\Subscription;

class CheckSubscriptionStatus
{
    public function execute(Subscription $subscription): Subscription
    {
        return $subscription->fresh();
    }
}
