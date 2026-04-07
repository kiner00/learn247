<?php

namespace App\Actions\Billing;

use App\Models\Community;
use App\Models\CurzzoTopup;
use App\Models\User;
use App\Services\XenditService;
use App\Support\InvoiceBuilder;
use Illuminate\Support\Facades\Log;

class StartCurzzoTopupCheckout
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{topup: CurzzoTopup, checkout_url: string}
     */
    public function execute(User $user, Community $community, array $pack, ?string $successRedirectUrl = null): array
    {
        try {
            $messages   = (int) $pack['messages'];
            $price      = (float) $pack['price'];
            $label      = $pack['label'] ?? ($messages > 0 ? "{$messages} Messages" : 'Unlimited Day Pass');
            $externalId = "curzzo_topup_{$community->id}_user_{$user->id}_" . time();

            $invoice = $this->xendit->createInvoice(
                InvoiceBuilder::make()
                    ->externalId($externalId)
                    ->amount($price)
                    ->currency($community->currency ?? 'PHP')
                    ->description("Curzzo Top-up: {$label} — {$community->name}")
                    ->customer($user)
                    ->successUrl($successRedirectUrl ?? config('app.url') . "/communities/{$community->slug}/curzzos")
                    ->failureUrl(config('app.url') . "/communities/{$community->slug}/curzzos")
                    ->item("Curzzo Top-up: {$label}", $price, 'AI Bot Top-up')
                    ->toArray()
            );

            $topup = CurzzoTopup::create([
                'user_id'      => $user->id,
                'community_id' => $community->id,
                'xendit_id'    => $invoice['id'],
                'status'       => CurzzoTopup::STATUS_PENDING,
                'messages'     => $messages,
            ]);

            return ['topup' => $topup, 'checkout_url' => $invoice['invoice_url']];
        } catch (\Throwable $e) {
            Log::error('StartCurzzoTopupCheckout failed', [
                'user_id'      => $user->id,
                'community_id' => $community->id,
                'error'        => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
