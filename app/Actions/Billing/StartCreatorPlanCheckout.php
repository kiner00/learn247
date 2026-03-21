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
    public function execute(User $user, string $plan): array
    {
        if (! in_array($plan, [CreatorSubscription::PLAN_BASIC, CreatorSubscription::PLAN_PRO])) {
            throw ValidationException::withMessages(['plan' => 'Invalid plan selected.']);
        }

        $currentPlan = $user->creatorPlan();
        if ($currentPlan === $plan) {
            throw ValidationException::withMessages(['plan' => 'You already have this plan active.']);
        }

        $priceKey = $plan === CreatorSubscription::PLAN_PRO
            ? 'creator_plan_pro_price'
            : 'creator_plan_basic_price';

        $defaultPrice = $plan === CreatorSubscription::PLAN_PRO ? 1999 : 499;
        $price        = (float) Setting::get($priceKey, $defaultPrice);

        $planLabel  = $plan === CreatorSubscription::PLAN_PRO ? 'Pro' : 'Basic';
        $externalId = "creator_plan_{$plan}_{$user->id}_" . time();

        $invoice = $this->xendit->createInvoice([
            'external_id' => $externalId,
            'amount'      => $price,
            'currency'    => 'PHP',
            'description' => "Creator {$planLabel} Plan — Monthly",
            'customer'    => ['given_names' => $user->name, 'email' => $user->email],
            'customer_notification_preference' => [
                'invoice_created' => ['email'],
                'invoice_paid'    => ['email'],
            ],
            'success_redirect_url' => config('app.url') . '/creator/plan?success=1',
            'failure_redirect_url' => config('app.url') . '/creator/plan?failed=1',
            'items' => [[
                'name'     => "Creator {$planLabel} Plan",
                'quantity' => 1,
                'price'    => $price,
                'category' => 'Creator Subscription',
            ]],
        ]);

        $creatorSubscription = CreatorSubscription::create([
            'user_id'            => $user->id,
            'plan'               => $plan,
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
