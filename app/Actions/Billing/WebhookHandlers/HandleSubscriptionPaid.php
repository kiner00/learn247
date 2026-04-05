<?php

namespace App\Actions\Billing\WebhookHandlers;

use App\Actions\Affiliate\RecordAffiliateConversion;
use App\Actions\Auth\IssueGuestPassword;
use App\Actions\Billing\SendChaChing;
use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Contracts\WebhookHandler;
use App\Events\SubscriptionPaid as SubscriptionPaidEvent;
use App\Models\CartEvent;
use App\Models\CommunityMember;
use App\Models\Affiliate;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\XenditService;
use App\Support\AffiliateCodeGenerator;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionPaid implements WebhookHandler
{
    private ?Subscription $subscription = null;

    public function __construct(
        private readonly SyncMembershipFromSubscription $syncMembership,
        private readonly RecordAffiliateConversion $recordConversion,
        private readonly SendChaChing $sendChaChing,
        private readonly IssueGuestPassword $issueGuestPassword,
    ) {}

    public function matches(string $xenditId): bool
    {
        $this->subscription = Subscription::with('community')
            ->where('xendit_id', $xenditId)
            ->first();

        return $this->subscription !== null;
    }

    public function handle(array $payload, string $eventId, string $status): void
    {
        $subscription = $this->subscription;

        if (! $subscription) {
            Log::warning('Xendit webhook: no matching subscription', ['xenditId' => $payload['id'] ?? null]);
            return;
        }

        try {
            $chaChing      = null;
            $guestMailData = null;

            DB::transaction(function () use ($subscription, $payload, $eventId, $status, &$chaChing, &$guestMailData) {
                $newSubStatus     = $this->mapSubscriptionStatus($status);
                $community        = $subscription->community;
                $pendingDeletion  = $community?->isPendingDeletion();
                $isOneTime        = $community?->billing_type === \App\Models\Community::BILLING_ONE_TIME;

                // One-time billing -> no expiry.
                // Monthly pending-deletion -> do NOT extend; let current expiry stand so subscriber finishes their paid period.
                // Monthly normal -> extend by 1 month on payment.
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
                    $gross          = (float) ($payload['amount'] ?? 0);
                    $channel        = $payload['payment_channel'] ?? '';
                    $processingFee  = XenditService::collectionFee($channel, $gross);
                    $platformFee    = round($gross * $community->platformFeeRate(), 2);

                    $payment = Payment::create([
                        'subscription_id'    => $subscription->id,
                        'community_id'       => $subscription->community_id,
                        'user_id'            => $subscription->user_id,
                        'amount'             => $gross,
                        'processing_fee'     => $processingFee,
                        'platform_fee'       => $platformFee,
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
                    // No affiliate -- still notify creator (skip if pending deletion)
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
                            Affiliate::create([
                                'community_id' => $community->id,
                                'user_id'      => $subscription->user_id,
                                'code'         => AffiliateCodeGenerator::generate(),
                                'status'       => Affiliate::STATUS_ACTIVE,
                            ]);
                        }
                    }
                }

                // Generate guest password inside transaction; email sent outside to avoid mail failures rolling back payment
                if ($payment && $paymentStatus === Payment::STATUS_PAID) {
                    $user = $subscription->user;
                    $tempPassword = $this->issueGuestPassword->generate($user);
                    if ($tempPassword) {
                        $guestMailData = [
                            'user'         => $user,
                            'password'     => $tempPassword,
                            'community'    => $subscription->community,
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

            // Flush cached analytics after successful payment processing
            if ($subscription->community) {
                CacheKeys::flushPayment(
                    $subscription->community_id,
                    $subscription->community->owner_id
                );
            }

            // Send emails outside transaction so mail errors never roll back payment
            if ($chaChing) {
                $this->sendChaChing->execute(
                    affiliateUser: $chaChing['affiliate_user'],
                    creator: $chaChing['creator'],
                    community: $chaChing['community'],
                    saleAmount: $chaChing['sale_amount'],
                    commission: $chaChing['commission'],
                    referredBy: $chaChing['referred_by'],
                );
            }

            if ($guestMailData) {
                $this->issueGuestPassword->sendEmail(
                    $guestMailData['user'],
                    $guestMailData['password'],
                    $guestMailData['community'],
                );
            }

            // Dispatch SubscriptionPaid event for email sequences
            if ($subscription->isActive() && $subscription->community) {
                $member = CommunityMember::where('community_id', $subscription->community_id)
                    ->where('user_id', $subscription->user_id)
                    ->first();

                if ($member) {
                    SubscriptionPaidEvent::dispatch($member, $subscription);
                }

                // Mark any cart events as completed
                CartEvent::where('community_id', $subscription->community_id)
                    ->where('user_id', $subscription->user_id)
                    ->where('event_type', CartEvent::TYPE_CHECKOUT_STARTED)
                    ->update(['event_type' => CartEvent::TYPE_PAYMENT_COMPLETED]);
            }
        } catch (\Throwable $e) {
            Log::error('HandleSubscriptionPaid failed', [
                'subscription_id' => $subscription->id,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
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
