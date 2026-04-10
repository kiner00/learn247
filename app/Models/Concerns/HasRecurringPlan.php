<?php

namespace App\Models\Concerns;

trait HasRecurringPlan
{
    public function isRecurring(): bool
    {
        return $this->xendit_plan_id !== null;
    }

    public function isAutoRenewing(): bool
    {
        return $this->isRecurring() && $this->recurring_status === 'ACTIVE';
    }
}
