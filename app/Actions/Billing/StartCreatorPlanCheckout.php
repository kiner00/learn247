<?php

namespace App\Actions\Billing;

use App\Models\CreatorSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Validation\ValidationException;

class StartCreatorPlanCheckout
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{creator_subscription: CreatorSubscription, checkout_url: string}
     * @throws ValidationException|\RuntimeException
     */
    public function execute(User $user): array
    {
        if ($user->hasActiveCreatorPlan()) {
            throw ValidationException::withMessages([
                'plan' => 'You already have an active Creator Pro plan.',
            ]);
        }

        $price = (float) Setting::get('creator_plan_discounted_price', 1999);

        $externalId = "creator_plan_{$user->id}_" . time();

        $invoice = $this->xendit->createInvoice([
            'external_id' => $externalId,
            'amount'      => $price,
            'currency'    => 'PHP',
            'description' => 'Creator Pro Plan — Monthly',
            'customer'    => ['given_names' => $user->name, 'email' => $user->email],
            'customer_notification_preference' => [
                'invoice_created' => ['email'],
                'invoice_paid'    => ['email'],
            ],
            'success_redirect_url' => config('app.url') . '/creator/plan?success=1',
            'failure_redirect_url' => config('app.url') . '/creator/plan?failed=1',
            'items' => [[
                'name'     => 'Creator Pro Plan',
                'quantity' => 1,
                'price'    => $price,
                'category' => 'Creator Subscription',
            ]],
        ]);

        $creatorSubscription = CreatorSubscription::create([
            'user_id'            => $user->id,
            'status'             => CreatorSubscription::STATUS_PENDING,
            'xendit_id'          => $invoice['id'],
            'xendit_invoice_url' => $invoice['invoice_url'],
        ]);

        return [
            'creator_subscription' => $creatorSubscription,
            'checkout_url'         => $invoice['invoice_url'],
        ];
    }
}
