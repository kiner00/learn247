<?php

namespace App\Actions\Billing;

use App\Models\Affiliate;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\User;
use App\Services\XenditService;
use App\Support\InvoiceBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StartCurzzoCheckout
{
    public function __construct(private readonly XenditService $xendit) {}

    /**
     * @return array{purchase: CurzzoPurchase, checkout_url: string}
     */
    public function execute(User $user, Curzzo $curzzo, ?string $affiliateCode = null, ?string $successRedirectUrl = null): array
    {
        if ($curzzo->isFree()) {
            throw ValidationException::withMessages([
                'curzzo' => 'This Curzzo is free. No checkout required.',
            ]);
        }

        $existing = CurzzoPurchase::where('curzzo_id', $curzzo->id)
            ->where('user_id', $user->id)
            ->where('status', CurzzoPurchase::STATUS_PAID)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'curzzo' => 'You already have access to this Curzzo.',
            ]);
        }

        try {
            $community  = $curzzo->community;
            $externalId = "curzzo_{$curzzo->id}_user_{$user->id}_" . time();

            $invoice = $this->xendit->createInvoice(
                InvoiceBuilder::make()
                    ->externalId($externalId)
                    ->amount((float) $curzzo->price)
                    ->currency($curzzo->currency ?? $community->currency ?? 'PHP')
                    ->description("Curzzo: {$curzzo->name} — {$community->name}")
                    ->customer($user)
                    ->successUrl($successRedirectUrl ?? config('app.url') . "/communities/{$community->slug}/curzzos")
                    ->failureUrl(config('app.url') . "/communities/{$community->slug}/curzzos")
                    ->item("Curzzo: {$curzzo->name}", (float) $curzzo->price, 'AI Bot')
                    ->toArray()
            );

            $affiliateId = null;
            if ($affiliateCode) {
                $affiliate = Affiliate::where('code', $affiliateCode)
                    ->where('community_id', $community->id)
                    ->where('status', Affiliate::STATUS_ACTIVE)
                    ->first();
                $affiliateId = $affiliate?->id;
            }

            $purchase = CurzzoPurchase::create([
                'user_id'      => $user->id,
                'curzzo_id'    => $curzzo->id,
                'affiliate_id' => $affiliateId,
                'status'       => CurzzoPurchase::STATUS_PENDING,
                'xendit_id'    => $invoice['id'],
            ]);

            return ['purchase' => $purchase, 'checkout_url' => $invoice['invoice_url']];
        } catch (\Throwable $e) {
            Log::error('StartCurzzoCheckout failed', [
                'user_id'   => $user->id,
                'curzzo_id' => $curzzo->id,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
