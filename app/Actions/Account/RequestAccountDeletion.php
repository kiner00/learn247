<?php

namespace App\Actions\Account;

use App\Actions\Billing\CancelRecurringPlan;
use App\Models\CourseEnrollment;
use App\Models\CreatorSubscription;
use App\Models\CurzzoPurchase;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequestAccountDeletion
{
    public function __construct(private readonly CancelRecurringPlan $cancel) {}

    public function execute(User $user): User
    {
        DB::transaction(function () use ($user) {
            $this->cancelAutoRenewingPlans($user);
            $user->tokens()->delete();
            $user->delete();
        });

        return $user->fresh();
    }

    private function cancelAutoRenewingPlans(User $user): void
    {
        $billables = [
            Subscription::where('user_id', $user->id)->where('recurring_status', 'ACTIVE')->get(),
            CreatorSubscription::where('user_id', $user->id)->where('recurring_status', 'ACTIVE')->get(),
            CurzzoPurchase::where('user_id', $user->id)->where('recurring_status', 'ACTIVE')->get(),
            CourseEnrollment::where('user_id', $user->id)->where('recurring_status', 'ACTIVE')->get(),
        ];

        foreach ($billables as $collection) {
            foreach ($collection as $entity) {
                try {
                    $this->cancel->execute($entity);
                } catch (\Throwable $e) {
                    Log::warning('RequestAccountDeletion: failed to cancel recurring plan; continuing', [
                        'user_id' => $user->id,
                        'entity_type' => $entity::class,
                        'entity_id' => $entity->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
}
