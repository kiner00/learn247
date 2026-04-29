<?php

namespace App\Queries\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\CartEvent;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Queries\Payout\CalculateEligibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class GetAffiliateStats
{
    public function getAffiliates(User $user): Collection
    {
        return Affiliate::where('user_id', $user->id)
            ->whereHas('community')
            ->with('community:id,name,slug')
            ->latest()
            ->get();
    }

    /**
     * Returns a fully mapped affiliate collection for dashboard display.
     * Single source of truth for affiliate data shape used by Web and API.
     */
    public function mapForDashboard(User $user, CalculateEligibility $eligibility): Collection
    {
        $affiliates = $this->getAffiliates($user);

        $activeRequestsByAffiliate = PayoutRequest::where('user_id', $user->id)
            ->where('type', PayoutRequest::TYPE_AFFILIATE)
            ->whereIn('status', [PayoutRequest::STATUS_PENDING, PayoutRequest::STATUS_APPROVED])
            ->get()
            ->keyBy('affiliate_id');

        return $affiliates->map(fn ($a) => [
            'id' => $a->id,
            'code' => $a->code,
            'status' => $a->status,
            'is_active' => $a->isActive(),
            'total_earned' => (float) $a->total_earned,
            'total_paid' => (float) $a->total_paid,
            'pending_amount' => (float) $a->pendingAmount(),
            'eligible_amount' => $eligibility->forAffiliate($a),
            'payout_request_status' => $activeRequestsByAffiliate->has($a->id)
                ? $activeRequestsByAffiliate->get($a->id)->status
                : null,
            'referral_url' => url("/ref/{$a->code}"),
            'community' => ['name' => $a->community->name, 'slug' => $a->community->slug],
            'facebook_pixel_id' => $a->facebook_pixel_id,
            'tiktok_pixel_id' => $a->tiktok_pixel_id,
            'google_analytics_id' => $a->google_analytics_id,
        ]);
    }

    /**
     * @return array{total_earned: float, total_paid: float, total_pending: float, total_conversions: int}
     */
    public function summary(Collection $affiliateIds, ?string $period = 'month'): array
    {
        $from = $this->periodStart($period);

        $base = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from));

        return [
            'total_earned' => (float) (clone $base)->sum('commission_amount'),
            'total_paid' => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PAID)->sum('commission_amount'),
            'total_pending' => (float) (clone $base)->where('status', AffiliateConversion::STATUS_PENDING)->sum('commission_amount'),
            'total_conversions' => (clone $base)->count(),
        ];
    }

    public function conversions(Collection $affiliateIds, ?string $period = 'month', int $limit = 100): Collection
    {
        $from = $this->periodStart($period);

        $earned = AffiliateConversion::whereIn('affiliate_id', $affiliateIds)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->with([
                'affiliate.community:id,name,slug',
                'referredUser:id,name,email,phone',
                'subscription.community:id,name',
                'courseEnrollment.course:id,title,community_id',
                'certificationPurchase.certification:id,name,community_id',
                'payment:id,metadata',
                'walletTransactions',
            ])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($c) => $this->mapEarnedRow($c));

        $abandoned = $this->abandonedRows($affiliateIds, $from);

        return $earned->concat($abandoned)
            ->sortByDesc('date_raw')
            ->values();
    }

    private function mapEarnedRow(AffiliateConversion $c): array
    {
        $credit = $c->walletTransactions->firstWhere('type', WalletTransaction::TYPE_CREDIT);
        $debit = $c->walletTransactions->firstWhere('type', WalletTransaction::TYPE_DEBIT);

        $status = $debit
            ? 'withdrawn'
            : ($credit?->status ?? ($c->status === AffiliateConversion::STATUS_PAID ? 'withdrawn' : 'settled'));

        return [
            'id' => $c->id,
            'reference' => sprintf('CV-%06d', $c->id),
            'date' => $c->created_at->toDateString(),
            'date_raw' => $c->created_at->toIso8601String(),
            'community' => $c->affiliate->community->name ?? '—',
            'description' => $this->describeConversion($c),
            'sale_amount' => (float) $c->sale_amount,
            'commission_amount' => (float) $c->commission_amount,
            'amount' => (float) $c->commission_amount,
            'is_lifetime' => (bool) $c->is_lifetime,
            'status' => $status,
            'available_at' => $credit?->available_at?->toDateString(),
            'paid_at' => $c->paid_at?->toDateString(),
            'referred_name' => $c->referredUser?->name,
            'referred_email' => $c->referredUser?->email,
            'referred_phone' => $c->referredUser?->phone,
            'payment_method' => $this->paymentMethod($c),
        ];
    }

    private function abandonedRows(Collection $affiliateIds, ?Carbon $from): Collection
    {
        if ($affiliateIds->isEmpty()) {
            return collect();
        }

        $codes = Affiliate::whereIn('id', $affiliateIds)->pluck('code')->all();
        if (empty($codes)) {
            return collect();
        }

        return CartEvent::where('event_type', CartEvent::TYPE_ABANDONED)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->whereIn('community_id', Affiliate::whereIn('id', $affiliateIds)->pluck('community_id'))
            ->with(['community:id,name', 'user:id,name,email,phone'])
            ->latest()
            ->limit(100)
            ->get()
            ->filter(fn ($e) => in_array($e->metadata['affiliate_code'] ?? null, $codes, true))
            ->map(fn ($e) => [
                'id' => $e->id,
                'reference' => sprintf('CE-%06d', $e->id),
                'date' => $e->created_at->toDateString(),
                'date_raw' => $e->created_at->toIso8601String(),
                'community' => $e->community->name ?? '—',
                'description' => 'Abandoned checkout',
                'sale_amount' => (float) ($e->metadata['amount'] ?? 0),
                'commission_amount' => 0.0,
                'amount' => 0.0,
                'is_lifetime' => false,
                'status' => 'pending',
                'available_at' => null,
                'paid_at' => null,
                'referred_name' => $e->user?->name,
                'referred_email' => $e->user?->email ?? $e->email,
                'referred_phone' => $e->user?->phone,
                'payment_method' => null,
            ])
            ->values();
    }

    private function describeConversion(AffiliateConversion $c): string
    {
        $community = $c->affiliate->community->name ?? 'Community';

        return match (true) {
            $c->course_enrollment_id !== null => 'Course: '.($c->courseEnrollment?->course?->title ?? $community),
            $c->certification_purchase_id !== null => 'Certification: '.($c->certificationPurchase?->certification?->name ?? $community),
            $c->curzzo_purchase_id !== null => 'Bot purchase — '.$community,
            default => $community.' subscription',
        };
    }

    private function paymentMethod(AffiliateConversion $c): ?string
    {
        $meta = $c->payment?->metadata ?? [];

        return $meta['payment_method'] ?? $meta['method'] ?? $meta['channel_code'] ?? null;
    }

    private function periodStart(?string $period): ?Carbon
    {
        return match ($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => null,
        };
    }
}
