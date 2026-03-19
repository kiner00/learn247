<?php

namespace App\Actions\Billing;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Mail\AffiliateChaChing;
use App\Mail\CreatorChaChing;
use App\Mail\TempPasswordMail;
use App\Models\Affiliate;
use App\Models\CourseEnrollment;
use App\Models\CreatorSubscription;
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

        $raw = $request->all();

        // Xendit v2 event-based format: { event: "invoice.paid", data: { id, status, ... } }
        // Fall back to v1 flat format: { id, status, ... }
        $event   = $raw['event'] ?? null;
        $payload = isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : $raw;

        // Skip non-invoice events (e.g. disbursement.completed, balance.updated)
        if ($event && ! str_starts_with($event, 'invoice.') && ! str_starts_with($event, 'payment.')) {
            Log::info('Xendit webhook: skipping non-invoice event', ['event' => $event]);
            return;
        }

        $status   = $payload['status']  ?? 'UNKNOWN';
        $xenditId = $payload['id']      ?? null;
        $eventId  = $xenditId ? "{$xenditId}_{$status}" : null;

        Log::info('Xendit webhook received', compact('xenditId', 'status', 'eventId', 'event'));

        // ── 2. Idempotency guard ───────────────────────────────────────────────
        if ($eventId && Payment::where('xendit_event_id', $eventId)->exists()) {
            Log::info('Xendit webhook: already processed', compact('eventId'));
            return;
        }

        // ── 3a. Check if this is a course enrollment invoice ──────────────────
        $enrollment = CourseEnrollment::with(['course.community', 'affiliate.user'])->where('xendit_id', $xenditId)->first();
        if ($enrollment) {
            $paymentStatus = $this->mapPaymentStatus($status);
            if ($paymentStatus === Payment::STATUS_PAID) {
                $isMonthly  = $enrollment->course?->access_type === \App\Models\Course::ACCESS_PAID_MONTHLY;
                $expiresAt  = $isMonthly
                    ? ($enrollment->expires_at?->isFuture() ? $enrollment->expires_at->addMonth() : now()->addMonth())
                    : null;
                $enrollment->update([
                    'status'     => CourseEnrollment::STATUS_PAID,
                    'paid_at'    => now(),
                    'expires_at' => $expiresAt,
                ]);
                Log::info('Xendit webhook: course enrollment paid', ['enrollment_id' => $enrollment->id, 'monthly' => $isMonthly]);

                // Record affiliate commission for course purchase
                $courseChaChing = null;
                $conversion = $this->recordConversion->executeForCourse($enrollment);
                if ($conversion) {
                    $courseChaChing = [
                        'affiliate_user' => $enrollment->affiliate->user,
                        'creator'        => $enrollment->course->community->owner,
                        'community'      => $enrollment->course->community,
                        'sale_amount'    => $conversion['sale_amount'],
                        'commission'     => $conversion['commission'],
                        'referred_by'    => $enrollment->affiliate->user->name,
                        'course_title'   => $enrollment->course->title,
                    ];
                }

                if ($courseChaChing) {
                    try {
                        Mail::to($courseChaChing['affiliate_user']->email)->queue(
                            new AffiliateChaChing(
                                $courseChaChing['affiliate_user'],
                                $courseChaChing['community'],
                                $courseChaChing['sale_amount'],
                                $courseChaChing['commission'],
                            )
                        );
                        if ($courseChaChing['creator']) {
                            Mail::to($courseChaChing['creator']->email)->queue(
                                new CreatorChaChing(
                                    $courseChaChing['creator'],
                                    $courseChaChing['community'],
                                    $courseChaChing['sale_amount'],
                                    $courseChaChing['referred_by'],
                                )
                            );
                        }
                    } catch (\Throwable $e) {
                        Log::error('Failed to send course cha-ching email', ['error' => $e->getMessage()]);
                    }
                }
            }
            return;
        }

        // ── 3b. Check if this is a creator plan invoice ───────────────────────
        $creatorSub = CreatorSubscription::with('user')->where('xendit_id', $xenditId)->first();
        if ($creatorSub) {
            $newStatus = match (strtoupper($status)) {
                'PAID', 'SETTLED' => CreatorSubscription::STATUS_ACTIVE,
                'EXPIRED'         => CreatorSubscription::STATUS_EXPIRED,
                'FAILED'          => CreatorSubscription::STATUS_CANCELLED,
                default           => CreatorSubscription::STATUS_PENDING,
            };

            if ($newStatus === CreatorSubscription::STATUS_ACTIVE) {
                $expiresAt = $creatorSub->expires_at && $creatorSub->expires_at->isFuture()
                    ? $creatorSub->expires_at->addMonth()
                    : now()->addMonth();

                $creatorSub->update(['status' => $newStatus, 'expires_at' => $expiresAt]);

                // Record idempotency payment row so this event isn't re-processed
                Payment::create([
                    'subscription_id'    => null,
                    'community_id'       => null,
                    'user_id'            => $creatorSub->user_id,
                    'amount'             => $payload['amount']   ?? 0,
                    'currency'           => $payload['currency'] ?? 'PHP',
                    'status'             => Payment::STATUS_PAID,
                    'provider_reference' => $payload['payment_id'] ?? ($payload['external_id'] ?? null),
                    'xendit_event_id'    => $eventId,
                    'metadata'           => $payload,
                    'paid_at'            => now(),
                ]);

                Log::info('Xendit webhook: creator plan activated', ['user_id' => $creatorSub->user_id]);
            } else {
                $creatorSub->update(['status' => $newStatus]);
                Log::info('Xendit webhook: creator plan status updated', ['status' => $newStatus, 'user_id' => $creatorSub->user_id]);
            }

            return;
        }

        // ── 3. Resolve subscription ────────────────────────────────────────────
        $subscription = Subscription::with('community')->where('xendit_id', $xenditId)->first();

        if (! $subscription) {
            Log::warning('Xendit webhook: no matching subscription', compact('xenditId'));
            return;
        }

        // ── 4. Persist + sync inside a transaction ─────────────────────────────
        $guestMailData     = null;
        $chaChing          = null;

        DB::transaction(function () use ($subscription, $payload, $eventId, $status, &$guestMailData, &$chaChing) {
            $newSubStatus     = $this->mapSubscriptionStatus($status);
            $community        = $subscription->community;
            $pendingDeletion  = $community?->isPendingDeletion();
            $isOneTime        = $community?->billing_type === \App\Models\Community::BILLING_ONE_TIME;

            // One-time billing → no expiry.
            // Monthly pending-deletion → do NOT extend; let current expiry stand so subscriber finishes their paid period.
            // Monthly normal → extend by 1 month on payment.
            if ($isOneTime) {
                $newExpiresAt = null;
            } elseif ($pendingDeletion) {
                $newExpiresAt = $subscription->expires_at; // keep as-is, no renewal
            } else {
                $newExpiresAt = $subscription->expires_at && $subscription->expires_at->isFuture()
                    ? $subscription->expires_at->addMonth()
                    : now()->addMonth();
            }

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
            // Skip cha-ching emails for pending-deletion communities (renewal payments on the way out)
            if ($payment && $paymentStatus === Payment::STATUS_PAID && $subscription->affiliate_id) {
                $this->recordConversion->execute($subscription->load('affiliate.community'), $payment);

                if (! $pendingDeletion) {
                    $affiliate     = $subscription->affiliate;
                    $affiliateUser = $affiliate->user;
                    $creator       = $community->owner;
                    $saleAmount    = (float) ($payload['amount'] ?? 0);
                    $rate          = $community->affiliate_commission_rate / 100;
                    $commission    = round($saleAmount * $rate, 2);

                    $chaChing = [
                        'affiliate_user' => $affiliateUser,
                        'creator'        => $creator,
                        'community'      => $community,
                        'sale_amount'    => $saleAmount,
                        'commission'     => $commission,
                        'referred_by'    => $affiliateUser->name,
                    ];
                }
            } elseif ($payment && $paymentStatus === Payment::STATUS_PAID && ! $pendingDeletion) {
                // No affiliate — still notify creator (skip if pending deletion)
                $creator  = $community->owner;
                $chaChing = [
                    'affiliate_user' => null,
                    'creator'        => $creator,
                    'community'      => $community,
                    'sale_amount'    => (float) ($payload['amount'] ?? 0),
                    'commission'     => null,
                    'referred_by'    => null,
                ];
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

            // Auto-delete the community when the last active subscriber expires/cancels
            if ($pendingDeletion && in_array($newSubStatus, [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELLED])) {
                $remainingActive = $community->activeSubscribersCount();
                if ($remainingActive === 0) {
                    Log::info('Graceful deletion: all subscribers expired, deleting community', ['community_id' => $community->id]);
                    $community->delete();
                }
            }
        });

        // ── 5. Send email outside transaction so mail errors never roll back payment ──

        // Cha-ching emails
        if ($chaChing) {
            try {
                // Affiliate cha-ching
                if ($chaChing['affiliate_user']) {
                    Mail::to($chaChing['affiliate_user']->email)->queue(
                        new AffiliateChaChing($chaChing['affiliate_user'], $chaChing['community'], $chaChing['sale_amount'], $chaChing['commission'])
                    );
                }
                // Creator cha-ching
                if ($chaChing['creator']) {
                    Mail::to($chaChing['creator']->email)->queue(
                        new CreatorChaChing($chaChing['creator'], $chaChing['community'], $chaChing['sale_amount'], $chaChing['referred_by'])
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send cha-ching email', ['error' => $e->getMessage()]);
            }
        }

        if ($guestMailData) {
            try {
                Mail::to($guestMailData['user']->email)->queue(
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
