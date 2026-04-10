<?php

namespace App\Billing\Strategies;

use App\Billing\CheckoutContext;
use App\Billing\CheckoutResult;
use App\Contracts\CheckoutStrategy;
use App\Services\XenditService;
use App\Support\RecurringPlanBuilder;

class RecurringCheckoutStrategy implements CheckoutStrategy
{
    public function __construct(private readonly XenditService $xendit) {}

    public function initiatePayment(CheckoutContext $context): CheckoutResult
    {
        $customerId = $context->user->ensureXenditCustomer($this->xendit);

        $plan = $this->xendit->createRecurringPlan(
            RecurringPlanBuilder::make()
                ->referenceId($context->referenceId)
                ->customerId($customerId)
                ->amount($context->amount)
                ->currency($context->currency)
                ->description($context->description)
                ->monthlyInterval()
                ->chargeImmediately()
                ->retryConfig(totalRetry: 3, intervalDays: 1)
                ->successReturnUrl($context->successUrl)
                ->failureReturnUrl($context->failureUrl)
                ->toArray()
        );

        $linkingUrl = $plan['actions'][0]['url'] ?? $plan['invoice_url'] ?? '';

        return new CheckoutResult(
            checkoutUrl: $linkingUrl,
            planId: $plan['id'],
            customerId: $customerId,
            recurringStatus: $plan['status'] ?? 'REQUIRES_ACTION',
        );
    }
}
