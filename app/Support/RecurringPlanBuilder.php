<?php

namespace App\Support;

use Carbon\Carbon;

class RecurringPlanBuilder
{
    private array $data = [];

    private array $schedule = [];

    public static function make(): self
    {
        return new self;
    }

    public function referenceId(string $id): self
    {
        $this->data['reference_id'] = $id;

        return $this;
    }

    public function customerId(string $customerId): self
    {
        $this->data['customer_id'] = $customerId;

        return $this;
    }

    public function amount(float $amount): self
    {
        $this->data['amount'] = $amount;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->data['currency'] = $currency;

        return $this;
    }

    public function description(string $description): self
    {
        $this->data['description'] = $description;

        return $this;
    }

    public function monthlyInterval(int $count = 1): self
    {
        $this->schedule['interval'] = 'MONTH';
        $this->schedule['interval_count'] = $count;

        return $this;
    }

    public function chargeImmediately(): self
    {
        $this->data['immediate_action_type'] = 'FULL_AMOUNT';

        return $this;
    }

    public function skipImmediateCharge(): self
    {
        unset($this->data['immediate_action_type']);

        return $this;
    }

    public function anchorDate(Carbon $date): self
    {
        $this->schedule['anchor_date'] = $date->toIso8601String();

        return $this;
    }

    public function retryConfig(int $totalRetry = 3, int $intervalDays = 1): self
    {
        $this->schedule['total_retry'] = $totalRetry;
        $this->schedule['retry_interval'] = 'DAY';
        $this->schedule['retry_interval_count'] = $intervalDays;
        $this->schedule['failed_attempt_notifications'] = range(1, $totalRetry);

        return $this;
    }

    public function successReturnUrl(string $url): self
    {
        $this->data['success_return_url'] = $url;

        return $this;
    }

    public function failureReturnUrl(string $url): self
    {
        $this->data['failure_return_url'] = $url;

        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->data['metadata'] = $metadata;

        return $this;
    }

    public function toArray(): array
    {
        $payload = $this->data;
        $payload['recurring_action'] = 'PAYMENT';
        $payload['failed_cycle_action'] = 'STOP';

        if (! empty($this->schedule)) {
            $this->schedule['reference_id'] = ($payload['reference_id'] ?? '').'_schedule';
            $payload['schedule'] = $this->schedule;
        }

        return $payload;
    }
}
