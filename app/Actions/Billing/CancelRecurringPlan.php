<?php

namespace App\Actions\Billing;

use App\Services\XenditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CancelRecurringPlan
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * Deactivate a Xendit recurring plan. The entity keeps access until expires_at.
     *
     * @param  Model  $entity  Any model using the HasRecurringPlan trait
     *
     * @throws \RuntimeException
     */
    public function execute(Model $entity): void
    {
        if (! $entity->xendit_plan_id) {
            throw new \RuntimeException('This subscription is not on a recurring plan.');
        }

        if ($entity->recurring_status === 'INACTIVE') {
            return; // already cancelled
        }

        try {
            $this->xendit->deactivateRecurringPlan($entity->xendit_plan_id);

            $entity->update(['recurring_status' => 'INACTIVE']);

            Log::info('Recurring plan cancelled', [
                'entity_type' => get_class($entity),
                'entity_id' => $entity->id,
                'plan_id' => $entity->xendit_plan_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('CancelRecurringPlan failed', [
                'entity_type' => get_class($entity),
                'entity_id' => $entity->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
