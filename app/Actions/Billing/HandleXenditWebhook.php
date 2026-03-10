<?php

namespace App\Actions\Billing;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Mail\TempPasswordMail;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\XenditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class HandleXenditWebhook
{
    public function __construct(
        private readonly XenditService $xendit,
        private readonly SyncMembershipFromSubscription $syncMembership,
        private readonly RecordAffiliateConversion $recordConversion,
    ) {}

    /**
     * Verify, deduplicate, and process a Xendit invoice webhook.
     *
     * Idempotency key: "{invoice_id}_{STATUS}" ensures each invoice×status
     * transition is processed exactly once.
     *
     * @throws HttpException on invalid callback token
     */
    public function execute(Request $request): void
    {
        // ── 1. Verify callback token ───────────────────────────────────────────
        if (! $this->xendit->verifyCallbackToken($request->header('x-callback-token'))) {
            Log::warning('Xendit webhook: invalid callback token');
            throw new HttpException(401, 'Invalid Xendit callback token.');
        }

        $payload  = $request->all();
        $status   = $payload['status']  ?? 'UNKNOWN';
        $xenditId = $payload['id']      ?? null;
        $eventId  = $xenditId ? "{$xenditId}_{$status}" : null;

        Log::info('Xendit webhook received', compact('xenditId', 'status', 'eventId'));

        // ── 2. Idempotency guard ───────────────────────────────────────────────
        if ($eventId && Payment::where('xendit_event_id', $eventId)->exists()) {
            Log::info('Xendit webhook: already processed', compact('eventId'));
            return;
        }

        // ── 3. Resolve subscription ────────────────────────────────────────────
        $subscription = Subscription::where('xendit_id', $xenditId)->first();

        if (! $subscription) {
            Log::warning('Xendit webhook: no matching subscription', compact('xenditId'));
            return;
        }

        // ── 4. Persist + sync inside a transaction ─────────────────────────────
        DB::transaction(function () use ($subscription, $payload, $eventId, $status) {
            $newSubStatus = $this->mapSubscriptionStatus($status);

            // Extend from existing expires_at so early renewals don't lose remaining days
            $newExpiresAt = $subscription->expires_at && $subscription->expires_at->isFuture()
                ? $subscription->expires_at->addMonth()
                : now()->addMonth();

            $subscription->update([
                'status'     => $newSubStatus,
                'expires_at' => $newSubStatus === Subscription::STATUS_ACTIVE
                    ? $newExpiresAt
                    : $subscription->expires_at,
            ]);

            // Only create payment records for terminal states
            $paymentStatus = $this->mapPaymentStatus($status);
            $payment = null;
            if ($paymentStatus !== Payment::STATUS_PENDING) {
                $payment = Payment::create([
                    'subscription_id'    => $subscription->id,
                    'community_id'       => $subscription->community_id,
                    'user_id'            => $subscription->user_id,
                    'amount'             => $payload['amount']      ?? 0,
                    'currency'           => $payload['currency']    ?? 'PHP',
                    'status'             => $paymentStatus,
                    'provider_reference' => $payload['payment_id']  ?? ($payload['external_id'] ?? null),
                    'xendit_event_id'    => $eventId,
                    'metadata'           => $payload,
                    'paid_at'            => $paymentStatus === Payment::STATUS_PAID ? now() : null,
                ]);
            }

            // Record affiliate commission if this subscription came via a referral
            if ($payment && $paymentStatus === Payment::STATUS_PAID && $subscription->affiliate_id) {
                $this->recordConversion->execute($subscription->load('affiliate.community'), $payment);
            }

            // Send temporary password email for guest checkouts
            if ($payment && $paymentStatus === Payment::STATUS_PAID) {
                $user = $subscription->user;
                if ($user->needs_password_setup) {
                    $tempPassword = 'Tmp@' . Str::upper(Str::random(3)) . Str::random(3);
                    $user->forceFill(['password' => Hash::make($tempPassword)])->save();

                    Mail::to($user->email)->send(
                        new TempPasswordMail($user, $tempPassword, $subscription->community)
                    );
                }
            }

            $this->syncMembership->execute($subscription->fresh());
        });
    }

    private function mapSubscriptionStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => Subscription::STATUS_ACTIVE,
            'EXPIRED'         => Subscription::STATUS_EXPIRED,
            'FAILED'          => Subscription::STATUS_CANCELLED,
            default           => Subscription::STATUS_PENDING,
        };
    }

    private function mapPaymentStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SETTLED' => Payment::STATUS_PAID,
            'EXPIRED'         => Payment::STATUS_EXPIRED,
            'FAILED'          => Payment::STATUS_FAILED,
            default           => Payment::STATUS_PENDING,
        };
    }
}
