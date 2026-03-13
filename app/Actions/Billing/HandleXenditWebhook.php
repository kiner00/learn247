<?php

namespace App\Actions\Billing;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Mail\TempPasswordMail;
use App\Models\Affiliate;
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
        $subscription = Subscription::with('community')->where('xendit_id', $xenditId)->first();

        if (! $subscription) {
            Log::warning('Xendit webhook: no matching subscription', compact('xenditId'));
            return;
        }

        // ── 4. Persist + sync inside a transaction ─────────────────────────────
        $guestMailData = null;

        DB::transaction(function () use ($subscription, $payload, $eventId, $status, &$guestMailData) {
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

            // Auto-create affiliate/invite code for every paid subscriber
            if ($payment && $paymentStatus === Payment::STATUS_PAID) {
                $community = $subscription->community;
                if ($community) {
                    $alreadyAffiliate = Affiliate::where('community_id', $community->id)
                        ->where('user_id', $subscription->user_id)
                        ->exists();

                    if (! $alreadyAffiliate) {
                        do {
                            $code = Str::random(12);
                        } while (Affiliate::where('code', $code)->exists());

                        Affiliate::create([
                            'community_id' => $community->id,
                            'user_id'      => $subscription->user_id,
                            'code'         => $code,
                            'status'       => Affiliate::STATUS_ACTIVE,
                        ]);
                    }
                }
            }

            // Prepare guest password email data (sent after transaction to avoid mail failures rolling back payment)
            if ($payment && $paymentStatus === Payment::STATUS_PAID) {
                $user = $subscription->user;
                if ($user->needs_password_setup) {
                    $tempPassword = 'Tmp@' . Str::upper(Str::random(3)) . Str::random(3);
                    $user->forceFill(['password' => Hash::make($tempPassword)])->save();

                    Log::info('Guest temp password issued', [
                        'email'    => $user->email,
                        'password' => $tempPassword,
                    ]);

                    $guestMailData = [
                        'user'      => $user,
                        'password'  => $tempPassword,
                        'community' => $subscription->community,
                    ];
                }
            }

            $this->syncMembership->execute($subscription->fresh());
        });

        // ── 5. Send email outside transaction so mail errors never roll back payment ──
        if ($guestMailData) {
            try {
                Mail::to($guestMailData['user']->email)->send(
                    new TempPasswordMail($guestMailData['user'], $guestMailData['password'], $guestMailData['community'])
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send guest temp password email', [
                    'email' => $guestMailData['user']->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
