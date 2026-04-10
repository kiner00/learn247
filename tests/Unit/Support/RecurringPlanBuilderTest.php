<?php

namespace Tests\Unit\Support;

use App\Support\RecurringPlanBuilder;
use Tests\TestCase;

class RecurringPlanBuilderTest extends TestCase
{
    public function test_builds_basic_payload(): void
    {
        $payload = RecurringPlanBuilder::make()
            ->referenceId('test_ref_001')
            ->customerId('cust_123')
            ->amount(499)
            ->currency('PHP')
            ->description('Test subscription')
            ->monthlyInterval()
            ->chargeImmediately()
            ->retryConfig(totalRetry: 3, intervalDays: 1)
            ->successReturnUrl('https://example.com/success')
            ->failureReturnUrl('https://example.com/failure')
            ->toArray();

        $this->assertEquals('test_ref_001', $payload['reference_id']);
        $this->assertEquals('cust_123', $payload['customer_id']);
        $this->assertEquals(499, $payload['amount']);
        $this->assertEquals('PHP', $payload['currency']);
        $this->assertEquals('Test subscription', $payload['description']);
        $this->assertEquals('PAYMENT', $payload['recurring_action']);
        $this->assertEquals('STOP', $payload['failed_cycle_action']);
        $this->assertEquals('FULL_AMOUNT', $payload['immediate_action_type']);
        $this->assertEquals('https://example.com/success', $payload['success_return_url']);
        $this->assertEquals('https://example.com/failure', $payload['failure_return_url']);
    }

    public function test_schedule_is_correctly_set(): void
    {
        $payload = RecurringPlanBuilder::make()
            ->referenceId('sched_ref')
            ->customerId('cust_456')
            ->amount(100)
            ->currency('PHP')
            ->monthlyInterval()
            ->retryConfig(totalRetry: 2, intervalDays: 3)
            ->toArray();

        $this->assertArrayHasKey('schedule', $payload);
        $this->assertEquals('MONTH', $payload['schedule']['interval']);
        $this->assertEquals(1, $payload['schedule']['interval_count']);
        $this->assertEquals(2, $payload['schedule']['total_retry']);
        $this->assertEquals('DAY', $payload['schedule']['retry_interval']);
        $this->assertEquals(3, $payload['schedule']['retry_interval_count']);
        $this->assertEquals([1, 2], $payload['schedule']['failed_attempt_notifications']);
        $this->assertEquals('sched_ref_schedule', $payload['schedule']['reference_id']);
    }

    public function test_metadata_is_included(): void
    {
        $payload = RecurringPlanBuilder::make()
            ->referenceId('meta_ref')
            ->customerId('cust_789')
            ->amount(200)
            ->currency('PHP')
            ->metadata(['entity_type' => 'subscription', 'entity_id' => 42])
            ->toArray();

        $this->assertEquals(['entity_type' => 'subscription', 'entity_id' => 42], $payload['metadata']);
    }

    public function test_custom_monthly_interval_count(): void
    {
        $payload = RecurringPlanBuilder::make()
            ->referenceId('bi_monthly')
            ->customerId('cust_000')
            ->amount(999)
            ->currency('PHP')
            ->monthlyInterval(2)
            ->toArray();

        $this->assertEquals(2, $payload['schedule']['interval_count']);
    }
}
