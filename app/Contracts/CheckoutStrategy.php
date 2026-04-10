<?php

namespace App\Contracts;

use App\Billing\CheckoutContext;
use App\Billing\CheckoutResult;

interface CheckoutStrategy
{
    public function initiatePayment(CheckoutContext $context): CheckoutResult;
}
